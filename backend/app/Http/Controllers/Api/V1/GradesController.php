<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Grades\RecordGradesRequest;
use App\Http\Requests\Grades\StoreAssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\ParentGuardian;
use App\Services\Grades\GradesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class GradesController extends ApiController
{
    public function __construct(
        private readonly GradesService $grades,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('grade.view'), 403);

        $q = Assessment::query()->with(['subject', 'term']);
        if ($request->user()?->isTeacher()) {
            $teacherId = Teacher::resolveForUser($request->user())?->id;
            $q->whereExists(function ($query) use ($teacherId): void {
                $query->selectRaw('1')
                    ->from('class_subject')
                    ->whereColumn('class_subject.class_id', 'assessments.class_id')
                    ->whereColumn('class_subject.subject_id', 'assessments.subject_id')
                    ->where('class_subject.teacher_id', $teacherId);
            });
        }
        foreach (['term_id', 'class_id', 'subject_id', 'teacher_id', 'status'] as $f) {
            if ($request->filled($f)) $q->where($f, $request->input($f));
        }

        $assessments = $q->orderByDesc('date')->limit(100)->get();
        return $this->ok(AssessmentResource::collection($assessments), 'Assessments retrieved.');
    }

    public function storeGradeSheet(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->hasPermission('grade.create')
            && $request->user()?->hasPermission('grade.record'),
            403,
        );

        $data = $request->validate([
            'term_id' => ['required', 'uuid', 'exists:terms,id'],
            'class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['quiz', 'test', 'sequence', 'exam', 'project', 'homework', 'other'])],
            'date' => ['required', 'date'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'weight' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'subjects' => ['required', 'array', 'min:1', 'max:50'],
            'subjects.*.subject_id' => ['required', 'uuid', 'exists:subjects,id', 'distinct'],
            'subjects.*.entries' => ['required', 'array', 'min:1', 'max:500'],
            'subjects.*.entries.*.student_id' => ['required', 'uuid', 'exists:students,id'],
            'subjects.*.entries.*.score' => [
                'nullable', 'numeric', 'min:0', 'max:' . (float) $request->input('max_score', 20),
            ],
        ]);

        $class = SchoolClass::query()->findOrFail($data['class_id']);
        $term = Term::query()->findOrFail($data['term_id']);
        if ($class->academic_year_id !== $term->academic_year_id) {
            throw ValidationException::withMessages([
                'term_id' => ['The selected term does not belong to this class academic year.'],
            ]);
        }

        $classStudentIds = Student::query()->where('class_id', $class->id)->pluck('id')->sort()->values();
        foreach ($data['subjects'] as $subjectRow) {
            $submittedStudentIds = collect($subjectRow['entries'])->pluck('student_id')->unique()->sort()->values();
            if ($submittedStudentIds->all() !== $classStudentIds->all()) {
                throw ValidationException::withMessages([
                    'roster' => ['The class roster changed while this gradebook was open. Refresh the page and enter marks for every current student.'],
                ]);
            }
        }

        if ($request->user()?->isTeacher()) {
            $teacherId = Teacher::resolveForUser($request->user())?->id;
            foreach ($data['subjects'] as $subjectRow) {
                abort_unless($teacherId && DB::table('class_subject')
                    ->where('class_id', $data['class_id'])
                    ->where('subject_id', $subjectRow['subject_id'])
                    ->where('teacher_id', $teacherId)->exists(), 403);
            }
        }

        $assessments = $this->grades->createGradeSheet($data);
        return $this->created(
            AssessmentResource::collection($assessments),
            'The complete grade sheet was saved successfully.',
        );
    }

    public function gradeSheets(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('grade.view'), 403);
        $filters = $request->validate([
            'class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'term_id' => ['required', 'uuid', 'exists:terms,id'],
        ]);

        $query = Assessment::query()
            ->whereNotNull('grade_sheet_id')
            ->where('class_id', $filters['class_id'])
            ->where('term_id', $filters['term_id'])
            ->with(['subject:id,name,code', 'entries.student:id,first_name,middle_name,last_name,admission_number']);

        if ($request->user()?->isTeacher()) {
            $teacherId = Teacher::resolveForUser($request->user())?->id;
            $query->whereExists(function ($subquery) use ($teacherId): void {
                $subquery->selectRaw('1')->from('class_subject')
                    ->whereColumn('class_subject.class_id', 'assessments.class_id')
                    ->whereColumn('class_subject.subject_id', 'assessments.subject_id')
                    ->where('class_subject.teacher_id', $teacherId);
            });
        }

        $sheets = $query->orderByDesc('date')->orderByDesc('created_at')->get()
            ->groupBy('grade_sheet_id')
            ->map(function ($assessments, string $sheetId): array {
                /** @var Assessment $first */
                $first = $assessments->first();
                $students = [];
                foreach ($assessments as $assessment) {
                    foreach ($assessment->entries as $entry) {
                        if (! $entry->student) continue;
                        $students[$entry->student_id] ??= [
                            'id' => $entry->student_id,
                            'full_name' => $entry->student->full_name,
                            'admission_number' => $entry->student->admission_number,
                            'scores' => [],
                        ];
                        $students[$entry->student_id]['scores'][$assessment->subject_id] =
                            $entry->score !== null ? (float) $entry->score : null;
                    }
                }
                return [
                    'id' => $sheetId,
                    'title' => $first->title,
                    'type' => $first->type,
                    'date' => $first->date?->toDateString(),
                    'max_score' => (float) $first->max_score,
                    'weight' => (float) $first->weight,
                    'status' => $assessments->every(fn (Assessment $item) => $item->status === Assessment::STATUS_PUBLISHED)
                        ? Assessment::STATUS_PUBLISHED : Assessment::STATUS_DRAFT,
                    'subjects' => $assessments->map(fn (Assessment $item) => [
                        'id' => $item->subject_id,
                        'name' => $item->subject?->name,
                        'code' => $item->subject?->code,
                        'assessment_id' => $item->id,
                    ])->values()->all(),
                    'students' => array_values($students),
                ];
            })->values()->all();

        return $this->ok($sheets, 'Grade sheets retrieved.');
    }

    public function updateGradeSheet(Request $request, string $sheetId): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('grade.record'), 403);
        $data = $request->validate([
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.assessment_id' => ['required', 'uuid'],
            'scores.*.student_id' => ['required', 'uuid'],
            'scores.*.score' => ['required', 'numeric', 'min:0'],
        ]);

        $assessments = Assessment::where('grade_sheet_id', $sheetId)->get()->keyBy('id');
        abort_if($assessments->isEmpty(), 404);
        $classId = $assessments->first()->class_id;
        $grouped = collect($data['scores'])->groupBy('assessment_id');
        $submittedStudentIds = collect($data['scores'])->pluck('student_id')->unique()->values();
        if (Student::query()->where('class_id', $classId)
            ->whereIn('id', $submittedStudentIds)->count() !== $submittedStudentIds->count()) {
            throw ValidationException::withMessages([
                'roster' => ['One or more students no longer belong to this class. Refresh the gradebook before saving corrections.'],
            ]);
        }

        foreach ($grouped as $assessmentId => $scores) {
            /** @var Assessment|null $assessment */
            $assessment = $assessments->get($assessmentId);
            abort_unless($assessment !== null, 422);
            abort_unless($scores->every(fn (array $score) =>
                (float) $score['score'] <= (float) $assessment->max_score
            ), 422);
            $this->ensureTeacherAssignment($request, $assessment);
        }

        DB::transaction(function () use ($grouped, $assessments): void {
            foreach ($grouped as $assessmentId => $scores) {
                $this->grades->recordEntries($assessments->get($assessmentId), $scores->all());
            }
        });

        return $this->ok(null, 'Grade sheet updated successfully.');
    }

    public function show(Assessment $assessment): JsonResponse
    {
        abort_unless(request()->user()?->hasPermission('grade.view'), 403);
        $this->ensureTeacherAssignment(request(), $assessment);
        $assessment = $this->grades->syncAssessmentStudents($assessment);
        return $this->ok(
            new AssessmentResource($assessment->load(['subject', 'term', 'entries.student'])),
            'Assessment retrieved.',
        );
    }

    public function store(StoreAssessmentRequest $request): JsonResponse
    {
        $a = $this->grades->createAssessment($request->validated());
        return $this->created(new AssessmentResource($a), 'Assessment created.');
    }

    public function update(StoreAssessmentRequest $request, Assessment $assessment): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('grade.edit'), 403);
        $this->ensureTeacherAssignment($request, $assessment);
        return $this->ok(
            new AssessmentResource($this->grades->updateAssessment($assessment, $request->validated())->load(['subject', 'term'])),
            'Assessment updated.',
        );
    }

    public function destroy(Request $request, Assessment $assessment): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('grade.delete'), 403);
        $this->ensureTeacherAssignment($request, $assessment);
        $this->grades->deleteAssessment($assessment);
        return $this->ok(null, 'Assessment deleted.');
    }

    public function publish(Request $request, Assessment $assessment): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('grade.publish'), 403);
        $this->ensureTeacherAssignment($request, $assessment);
        return $this->ok(
            new AssessmentResource($this->grades->publishAssessment($assessment)->load(['subject', 'term'])),
            'Assessment published.',
        );
    }

    public function recordEntries(RecordGradesRequest $request, Assessment $assessment): JsonResponse
    {
        $updated = $this->grades->recordEntries($assessment, $request->input('entries'));
        return $this->ok(
            new AssessmentResource($updated->load(['subject', 'term', 'entries.student'])),
            'Grades saved.',
        );
    }

    public function termReport(Request $request, string $studentId, string $termId): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('grade.view'), 403);
        /** @var Student $student */
        $student = Student::findOrFail($studentId);
        if ($request->user()?->isParent()) {
            abort_unless(ParentGuardian::where('user_id', $request->user()->id)
                ->whereHas('students', fn ($query) => $query->where('students.id', $student->id))
                ->exists(), 403);
        }
        /** @var Term $term */
        $term = Term::findOrFail($termId);

        return $this->ok([
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'admission_number' => $student->admission_number,
                'photo_url' => $student->photo_url,
            ],
            'term' => ['id' => $term->id, 'name' => $term->name],
            'report' => $this->grades->termReportForStudent($student, $term),
        ], 'Term report retrieved.');
    }

    public function classRanking(Request $request, string $classId, string $termId): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('grade.view'), 403);
        /** @var SchoolClass $class */
        $class = SchoolClass::findOrFail($classId);
        /** @var Term $term */
        $term = Term::findOrFail($termId);

        return $this->ok([
            'class' => ['id' => $class->id, 'name' => $class->name],
            'term'  => ['id' => $term->id, 'name' => $term->name],
            'ranking' => $this->grades->classRanking($class, $term),
        ], 'Class ranking computed.');
    }

    private function ensureTeacherAssignment(Request $request, Assessment $assessment): void
    {
        if (! $request->user()?->isTeacher()) {
            return;
        }

        $teacherId = Teacher::resolveForUser($request->user())?->id;
        abort_unless($teacherId !== null && DB::table('class_subject')
            ->where('class_id', $assessment->class_id)
            ->where('subject_id', $assessment->subject_id)
            ->where('teacher_id', $teacherId)
            ->exists(), 403);
    }
}
