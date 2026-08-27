<?php

declare(strict_types=1);

namespace Tests\Feature\Grades;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\GradeEntry;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class GradeSheetIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_multi_subject_sheet_is_saved_in_bulk_and_incomplete_roster_is_rejected(): void
    {
        $this->seed();
        $admin = User::query()->whereHas('role', fn ($query) => $query->where('name', 'administrator'))->firstOrFail();
        $year = AcademicYear::query()->where('school_id', $admin->school_id)->where('status', 'active')->firstOrFail();
        $class = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'education_system' => 'secondary_general',
            'name' => 'Form 1',
            'grade_level' => 'Form 1',
            'cycle' => 'first_cycle',
            'language' => 'en',
            'capacity' => 40,
            'is_active' => true,
        ]);
        $term = Term::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'First Term',
            'sequence' => 1,
            'start_date' => $year->start_date,
            'end_date' => $year->start_date->copy()->addMonths(3),
            'status' => Term::STATUS_ACTIVE,
        ]);
        $students = Student::factory()->count(3)->create([
            'school_id' => $admin->school_id,
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
        ]);
        $subjects = collect([
            ['name' => 'Mathematics', 'code' => 'MATH', 'coefficient' => 4, 'color' => '#2563EB'],
            ['name' => 'English', 'code' => 'ENG', 'coefficient' => 3, 'color' => '#16A34A'],
            ['name' => 'Economics', 'code' => 'ECO', 'coefficient' => 2, 'color' => '#EA580C'],
        ])->map(fn (array $data) => Subject::query()->create($data + [
            'school_id' => $admin->school_id,
            'education_system' => 'secondary_general',
            'is_active' => true,
        ]));
        Sanctum::actingAs($admin, ['*']);

        $subjectRows = $subjects->values()->map(fn (Subject $subject, int $subjectIndex) => [
            'subject_id' => $subject->id,
            'entries' => $students->values()->map(fn (Student $student, int $studentIndex) => [
                'student_id' => $student->id,
                'score' => 10 + $subjectIndex + $studentIndex,
            ])->all(),
        ])->all();
        $payload = [
            'term_id' => $term->id,
            'class_id' => $class->id,
            'title' => 'First sequence',
            'type' => 'sequence',
            'date' => $year->start_date->copy()->addMonth()->toDateString(),
            'max_score' => 20,
            'weight' => 1,
            'subjects' => $subjectRows,
        ];

        $this->postJson('/api/v1/grades/grade-sheets', $payload)->assertCreated()->assertJsonCount(3, 'data');
        $this->assertSame(3, Assessment::query()->count());
        $this->assertSame(9, GradeEntry::query()->count());

        $incomplete = $payload;
        $incomplete['title'] = 'Incomplete attempt';
        array_pop($incomplete['subjects'][0]['entries']);
        $this->postJson('/api/v1/grades/grade-sheets', $incomplete)
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.roster.0',
                'The class roster changed while this gradebook was open. Refresh the page and enter marks for every current student.',
            );
        $this->assertSame(3, Assessment::query()->count());
    }
}
