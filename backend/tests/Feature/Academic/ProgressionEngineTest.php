<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Enums\AcademicDecisionStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\GradeEntry;
use App\Models\PromotionPolicy;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Progression\PromotionEngine;
use App\Services\Progression\SchoolYearTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProgressionEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_configurable_rules_and_subject_coefficients(): void
    {
        [$enrollment] = $this->academicRecord([9, 20], [4, 1]);

        PromotionPolicy::create([
            'school_id' => $enrollment->school_id,
            'academic_year_id' => $enrollment->academic_year_id,
            'name' => 'Standard policy',
            'grading_scale' => 20,
            'passing_average' => 12,
            'minimum_subject_mark' => 8,
            'maximum_failed_subjects' => 2,
            'critical_subject_ids' => [],
            'allow_class_council_override' => true,
            'is_active' => true,
        ]);

        $decision = app(PromotionEngine::class)->evaluate($enrollment);

        $this->assertSame(AcademicDecisionStatus::Repeating, $decision->final_decision);
        $this->assertSame('11.20', $decision->final_average);
        $this->assertSame([], $decision->failed_subject_ids);
        $this->assertDatabaseHas('academic_decisions', [
            'student_id' => $enrollment->student_id,
            'academic_year_id' => $enrollment->academic_year_id,
            'final_decision' => 'repeating',
        ]);
    }

    public function test_exclusion_conditions_are_school_configurable(): void
    {
        [$enrollment] = $this->academicRecord([4, 5], [1, 1]);

        PromotionPolicy::create([
            'school_id' => $enrollment->school_id,
            'academic_year_id' => $enrollment->academic_year_id,
            'name' => 'Strict policy',
            'grading_scale' => 20,
            'passing_average' => 10,
            'minimum_subject_mark' => 8,
            'maximum_failed_subjects' => 1,
            'critical_subject_ids' => [],
            'exclusion_conditions' => ['average_below' => 6, 'match' => 'any'],
            'allow_class_council_override' => false,
            'is_active' => true,
        ]);

        $decision = app(PromotionEngine::class)->evaluate($enrollment);

        $this->assertSame(AcademicDecisionStatus::Excluded, $decision->final_decision);
    }

    public function test_rollover_preserves_identity_and_creates_one_new_enrollment(): void
    {
        [$enrollment] = $this->academicRecord([14, 16], [4, 1]);
        $fromYear = $enrollment->academicYear;
        $school = $enrollment->school;

        SchoolClass::create([
            'academic_year_id' => $fromYear->id,
            'name' => 'Form 2 Motor Mechanics',
            'grade_level' => 'Form 2',
            'next_grade_level' => 'Form 3',
            'speciality' => 'motor_mechanics',
            'cycle' => 'first_cycle',
            'language' => 'en',
            'capacity' => 40,
            'is_active' => true,
        ]);
        $toYear = AcademicYear::create([
            'school_id' => $school->id,
            'title' => '2027/2028',
            'start_date' => '2027-09-01',
            'end_date' => '2028-06-30',
            'status' => AcademicYear::STATUS_UPCOMING,
        ]);
        PromotionPolicy::create([
            'school_id' => $school->id,
            'academic_year_id' => $fromYear->id,
            'name' => 'Standard policy',
            'grading_scale' => 20,
            'passing_average' => 10,
            'minimum_subject_mark' => 8,
            'maximum_failed_subjects' => 2,
            'critical_subject_ids' => [],
            'allow_class_council_override' => true,
            'is_active' => true,
        ]);

        $transition = app(SchoolYearTransitionService::class)->rollover($fromYear, $toYear);
        $next = StudentEnrollment::where('student_id', $enrollment->student_id)
            ->where('academic_year_id', $toYear->id)
            ->firstOrFail();

        $this->assertSame('completed', $transition->status);
        $this->assertSame(1, $transition->summary['promoted']);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->fresh()->status);
        $this->assertSame('Form 2', $next->schoolClass->grade_level);
        $this->assertSame($enrollment->student_id, $next->student_id);
        $this->assertSame(2, StudentEnrollment::where('student_id', $enrollment->student_id)->count());
        $this->assertSame($toYear->id, $enrollment->student->fresh()->academic_year_id);
    }

    /**
     * @param array<int, float|int> $scores
     * @param array<int, float|int> $coefficients
     * @return array{StudentEnrollment, array<int, Subject>}
     */
    private function academicRecord(array $scores, array $coefficients): array
    {
        $school = School::factory()->create(['slug' => 'progression-school']);
        $year = AcademicYear::create([
            'school_id' => $school->id,
            'title' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::STATUS_ACTIVE,
        ]);
        $school->update(['current_academic_year_id' => $year->id]);
        $class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'Form 1 Motor Mechanics',
            'grade_level' => 'Form 1',
            'next_grade_level' => 'Form 2',
            'speciality' => 'motor_mechanics',
            'cycle' => 'first_cycle',
            'language' => 'en',
            'capacity' => 40,
            'is_active' => true,
        ]);
        $term = Term::create([
            'academic_year_id' => $year->id,
            'name' => 'Third Term',
            'sequence' => 3,
            'start_date' => '2027-04-01',
            'end_date' => '2027-06-30',
            'status' => Term::STATUS_CLOSED,
        ]);
        $student = Student::factory()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'enrollment_date' => '2026-09-01',
        ]);
        $enrollment = StudentEnrollment::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'enrolled_at' => '2026-09-01',
            'status' => EnrollmentStatus::Active->value,
        ]);

        $subjects = [];
        foreach ($scores as $index => $score) {
            $subject = Subject::create([
                'school_id' => $school->id,
                'name' => "Subject {$index}",
                'code' => "SUB{$index}",
                'coefficient' => $coefficients[$index],
                'color' => $index === 0 ? '#2563eb' : '#16a34a',
                'is_active' => true,
            ]);
            $class->subjects()->attach($subject->id, [
                'id' => (string) Str::uuid(),
                'coefficient' => $coefficients[$index],
                'weekly_frequency' => 3,
            ]);
            $assessment = Assessment::create([
                'term_id' => $term->id,
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'title' => 'Final examination',
                'type' => 'exam',
                'date' => '2027-06-01',
                'max_score' => 20,
                'weight' => 1,
                'status' => Assessment::STATUS_PUBLISHED,
            ]);
            GradeEntry::create([
                'assessment_id' => $assessment->id,
                'student_id' => $student->id,
                'score' => $score,
                'graded_at' => now(),
            ]);
            $subjects[] = $subject;
        }

        return [$enrollment, $subjects];
    }
}
