<?php

declare(strict_types=1);

namespace App\Services\Grades;

use App\Models\Assessment;
use App\Models\GradeEntry;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\StudentEnrollment;
use App\Services\BaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradesService extends BaseService
{
    /**
     * Create and fill one assessment per subject as a single transaction.
     *
     * @param array<string, mixed> $sheet
     * @return array<int, Assessment>
     */
    public function createGradeSheet(array $sheet): array
    {
        return $this->transaction(function () use ($sheet): array {
            $created = [];
            $sheetId = (string) \Illuminate\Support\Str::uuid();
            foreach ($sheet['subjects'] as $subjectRow) {
                $pivot = DB::table('class_subject')
                    ->where('class_id', $sheet['class_id'])
                    ->where('subject_id', $subjectRow['subject_id'])
                    ->first();

                $assessment = $this->createAssessment([
                    'grade_sheet_id' => $sheetId,
                    'term_id' => $sheet['term_id'],
                    'class_id' => $sheet['class_id'],
                    'subject_id' => $subjectRow['subject_id'],
                    'teacher_id' => $pivot?->teacher_id,
                    'title' => $sheet['title'],
                    'type' => $sheet['type'],
                    'date' => $sheet['date'],
                    'max_score' => $sheet['max_score'],
                    'weight' => $sheet['weight'],
                ]);
                $created[] = $this->recordEntries($assessment, $subjectRow['entries'])
                    ->load(['subject', 'term', 'entries.student']);
            }
            return $created;
        });
    }

    // ─── Assessments ────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $attributes
     */
    public function createAssessment(array $attributes): Assessment
    {
        return $this->transaction(function () use ($attributes): Assessment {
            $subject = Subject::findOrFail($attributes['subject_id']);

            // Administrators select from the school subject catalogue. If the
            // subject has not yet been linked to this class, link it now using
            // the administrator-defined subject coefficient.
            DB::table('class_subject')->insertOrIgnore([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'class_id' => $attributes['class_id'],
                'subject_id' => $subject->id,
                'teacher_id' => $attributes['teacher_id'] ?? null,
                'coefficient' => (float) $subject->coefficient,
                'weekly_frequency' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $a = Assessment::create($attributes + [
                'status'     => Assessment::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);

            // Pre-seed empty grade entries for every student in the class so
            // teachers can fill the grid without missing rows.
            $studentIds = Student::query()
                ->where('class_id', $a->class_id)
                ->whereNull('deleted_at')
                ->pluck('id');

            $rows = $studentIds->map(fn ($id) => [
                'id'            => (string) \Illuminate\Support\Str::uuid(),
                'assessment_id' => $a->id,
                'student_id'    => $id,
                'score'         => null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ])->all();
            if (! empty($rows)) {
                DB::table('grade_entries')->insert($rows);
            }

            return $a->load(['subject', 'class', 'term', 'teacher']);
        });
    }

    public function updateAssessment(Assessment $a, array $attributes): Assessment
    {
        $a->update($attributes);
        return $a->fresh() ?? $a;
    }

    public function publishAssessment(Assessment $a): Assessment
    {
        $a->update(['status' => Assessment::STATUS_PUBLISHED]);
        return $a->fresh() ?? $a;
    }

    public function deleteAssessment(Assessment $a): void
    {
        $a->delete();
    }

    // ─── Grade entries ──────────────────────────────────────────────

    /**
     * Bulk-save grade entries for an assessment.
     *
     * @param array<int, array{student_id:string, score:?float, comment?:?string, grade_letter?:?string}> $entries
     */
    public function recordEntries(Assessment $a, array $entries): Assessment
    {
        return $this->transaction(function () use ($a, $entries): Assessment {
            $studentIds = collect($entries)->pluck('student_id')->unique()->values();
            $validStudentIds = Student::query()
                ->where('class_id', $a->class_id)
                ->whereIn('id', $studentIds)
                ->pluck('id');
            if ($validStudentIds->count() !== $studentIds->count()) {
                throw ValidationException::withMessages([
                    'entries' => ['Every grade entry must belong to a student in the assessment class.'],
                ]);
            }

            $now = now();
            $rows = [];
            foreach ($entries as $e) {
                $score = $e['score'] ?? null;
                if ($score !== null && ($score < 0 || $score > (float) $a->max_score)) {
                    throw ValidationException::withMessages([
                        'entries' => ["Score for student {$e['student_id']} is outside 0..{$a->max_score}."],
                    ]);
                }
                $rows[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'assessment_id' => $a->id,
                    'student_id' => $e['student_id'],
                    'score' => $score,
                    'grade_letter' => $e['grade_letter'] ?? $this->letterFor($score, (float) $a->max_score),
                    'comment' => $e['comment'] ?? null,
                    'graded_by' => Auth::id(),
                    'graded_at' => $score !== null ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                DB::table('grade_entries')->upsert(
                    $rows,
                    ['assessment_id', 'student_id'],
                    ['score', 'grade_letter', 'comment', 'graded_by', 'graded_at', 'updated_at'],
                );
            }
            return $a->load('entries.student');
        });
    }

    /**
     * Add grade rows for students assigned to the class after an assessment
     * was created. Existing scores are never changed.
     */
    public function syncAssessmentStudents(Assessment $assessment): Assessment
    {
        $existing = GradeEntry::query()
            ->where('assessment_id', $assessment->id)
            ->pluck('student_id');

        $rows = Student::query()
            ->where('class_id', $assessment->class_id)
            ->whereNotIn('id', $existing)
            ->pluck('id')
            ->map(fn ($studentId) => [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'assessment_id' => $assessment->id,
                'student_id' => $studentId,
                'score' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

        if ($rows !== []) {
            DB::table('grade_entries')->insert($rows);
        }

        return $assessment->fresh() ?? $assessment;
    }

    // ─── Reporting ──────────────────────────────────────────────────

    /**
     * Compute the average for a single student across one subject in one term.
     * Each assessment contributes (score / max_score * 20) * weight; the
     * weighted scores divided by the total weight gives the subject grade.
     *
     * @return array{average: ?float, entries: int, total_weight: float}
     */
    public function subjectAverageForStudent(string $studentId, string $classId, string $subjectId, string $termId): array
    {
        $entries = GradeEntry::query()
            ->where('student_id', $studentId)
            ->whereNotNull('score')
            ->whereHas('assessment', fn ($q) => $q
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->where('term_id', $termId)
                ->where('status', Assessment::STATUS_PUBLISHED))
            ->with('assessment:id,max_score,weight')
            ->get();

        if ($entries->isEmpty()) {
            return ['average' => null, 'entries' => 0, 'total_weight' => 0.0];
        }

        $weightedSum = 0.0;
        $totalWeight = 0.0;
        foreach ($entries as $e) {
            $normalised  = (float) $e->score / (float) $e->assessment->max_score * 20.0;
            $weight      = (float) $e->assessment->weight;
            $weightedSum += $normalised * $weight;
            $totalWeight += $weight;
        }

        return [
            'average'     => $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : null,
            'entries'     => $entries->count(),
            'total_weight' => $totalWeight,
        ];
    }

    /**
     * Full term report for a student: per-subject averages + overall weighted average.
     *
     * @return array{
     *   subjects: array<int, array{subject_id:string, name:string, code:string, color:string, coefficient:float, average:?float, entries:int}>,
     *   overall_average: ?float,
     *   total_coefficient: float
     * }
     */
    public function termReportForStudent(Student $student, Term $term): array
    {
        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $term->academic_year_id)
            ->first();
        $classId = $enrollment?->class_id ?? $student->class_id;
        if (! $classId) {
            return ['subjects' => [], 'overall_average' => null, 'total_coefficient' => 0.0];
        }

        $class = SchoolClass::with('subjects')->findOrFail($classId);
        $rows = [];
        $weightedTotal = 0.0;
        $coefficientTotal = 0.0;

        foreach ($class->subjects as $subject) {
            /** @var Subject $subject */
            $coefficient = (float) $subject->pivot->coefficient;
            $stats = $this->subjectAverageForStudent($student->id, $class->id, $subject->id, $term->id);
            $rows[] = [
                'subject_id'   => $subject->id,
                'name'         => $subject->name,
                'code'         => $subject->code,
                'color'        => $subject->color,
                'coefficient'  => $coefficient,
                'average'      => $stats['average'],
                'entries'      => $stats['entries'],
            ];
            if ($stats['average'] !== null) {
                $weightedTotal     += $stats['average'] * $coefficient;
                $coefficientTotal  += $coefficient;
            }
        }

        return [
            'subjects'          => $rows,
            'overall_average'   => $coefficientTotal > 0 ? round($weightedTotal / $coefficientTotal, 2) : null,
            'total_coefficient' => $coefficientTotal,
        ];
    }

    /**
     * Class ranking for a term.
     *
     * @return array<int, array{rank:int, student:array{id:string, full_name:string, admission_number:string, photo_url:?string}, average:?float}>
     */
    public function classRanking(SchoolClass $class, Term $term): array
    {
        $students = $class->students()
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'admission_number', 'photo'])
            ->orderBy('last_name')
            ->get();
        $subjects = $class->subjects()->get()->keyBy('id');
        $totalSubjects = $subjects->count();
        $entriesByStudent = GradeEntry::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereNotNull('score')
            ->whereHas('assessment', fn ($query) => $query
                ->where('class_id', $class->id)
                ->where('term_id', $term->id)
                ->where('status', Assessment::STATUS_PUBLISHED))
            ->with('assessment:id,subject_id,max_score,weight')
            ->get()
            ->groupBy('student_id');

        $rows = [];
        foreach ($students as $s) {
            $weightedTotal = 0.0;
            $coefficientTotal = 0.0;
            $studentEntries = $entriesByStudent->get($s->id, collect())->groupBy(
                fn (GradeEntry $entry) => $entry->assessment->subject_id
            );

            foreach ($studentEntries as $subjectId => $subjectEntries) {
                $subject = $subjects->get($subjectId);
                if (! $subject) continue;

                $subjectWeighted = 0.0;
                $subjectWeight = 0.0;
                foreach ($subjectEntries as $entry) {
                    $assessmentWeight = (float) $entry->assessment->weight;
                    $subjectWeighted += ((float) $entry->score / (float) $entry->assessment->max_score * 20.0) * $assessmentWeight;
                    $subjectWeight += $assessmentWeight;
                }
                if ($subjectWeight <= 0) continue;

                $subjectAverage = $subjectWeighted / $subjectWeight;
                $coefficient = (float) $subject->pivot->coefficient;
                $weightedTotal += $subjectAverage * $coefficient;
                $coefficientTotal += $coefficient;
            }

            $rows[] = [
                'student' => [
                    'id'                => $s->id,
                    'full_name'         => $s->full_name,
                    'admission_number'  => $s->admission_number,
                    'photo_url'         => $s->photo_url,
                ],
                'average' => $coefficientTotal > 0 ? round($weightedTotal / $coefficientTotal, 2) : null,
                'graded_subjects' => $studentEntries->filter(fn ($entries) => $entries->isNotEmpty())->count(),
                'total_subjects' => $totalSubjects,
                'complete' => $totalSubjects > 0
                    && $studentEntries->filter(fn ($entries) => $entries->isNotEmpty())->count() === $totalSubjects,
            ];
        }

        // Sort by average desc, nulls last
        usort($rows, function ($a, $b): int {
            if ($a['average'] === null && $b['average'] === null) return 0;
            if ($a['average'] === null) return 1;
            if ($b['average'] === null) return -1;
            return $b['average'] <=> $a['average'];
        });

        // Dense-rank assignment
        $rank = 0;
        $previousAvg = null;
        $position = 0;
        foreach ($rows as &$row) {
            $position++;
            if ($row['average'] === null) {
                $row['rank'] = $position;
                continue;
            }
            if ($row['average'] !== $previousAvg) {
                $rank = $position;
                $previousAvg = $row['average'];
            }
            $row['rank'] = $rank;
        }

        return $rows;
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function letterFor(?float $score, float $max): ?string
    {
        if ($score === null) return null;
        $pct = $score / $max * 100;
        return match (true) {
            $pct >= 90 => 'A+', $pct >= 80 => 'A',
            $pct >= 70 => 'B',  $pct >= 60 => 'C',
            $pct >= 50 => 'D',  default    => 'F',
        };
    }
}
