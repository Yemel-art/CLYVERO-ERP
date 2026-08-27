<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::query()->whereHas('role', fn ($q) => $q->where('name', 'administrator'))->firstOrFail();
    }

    public function test_admin_can_create_an_academic_year_then_activate_it(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/academic-years', [
            'title'      => '2027/2028',
            'start_date' => '2027-09-01',
            'end_date'   => '2028-06-30',
        ]);
        $response->assertCreated()
            ->assertJsonPath('data.title', '2027/2028')
            ->assertJsonPath('data.status', AcademicYear::STATUS_UPCOMING);

        $yearId = $response->json('data.id');

        $activate = $this->postJson("/api/v1/academic-years/{$yearId}/activate");
        $activate->assertOk()->assertJsonPath('data.status', AcademicYear::STATUS_ACTIVE);
    }

    public function test_terms_have_a_unique_active_status_per_year(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $year = AcademicYear::query()->where('status', AcademicYear::STATUS_ACTIVE)->firstOrFail();

        $t1 = $this->postJson("/api/v1/academic-years/{$year->id}/terms", [
            'name' => 'First Term', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2026-12-15',
        ])->assertCreated()->json('data.id');

        $t2 = $this->postJson("/api/v1/academic-years/{$year->id}/terms", [
            'name' => 'Second Term', 'sequence' => 2,
            'start_date' => '2027-01-10', 'end_date' => '2027-03-30',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/terms/{$t1}/activate")->assertOk();
        $this->postJson("/api/v1/terms/{$t2}/activate")->assertOk();

        $this->assertSame(Term::STATUS_CLOSED,  Term::find($t1)->status);
        $this->assertSame(Term::STATUS_ACTIVE,  Term::find($t2)->status);
    }

    public function test_a_subject_can_be_attached_to_a_class_with_a_teacher(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $year = AcademicYear::query()->where('status', AcademicYear::STATUS_ACTIVE)->firstOrFail();

        $class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'name'             => 'Form 1A',
            'grade_level'      => 'Form 1',
            'section'          => 'A',
            'capacity'         => 40,
            'is_active'        => true,
        ]);
        $subject = Subject::create([
            'school_id' => $year->school_id,
            'name' => 'Mathematics',
            'code' => 'MATH',
            'coefficient' => 4,
            'color' => '#2563eb',
            'is_active' => true,
        ]);

        $teacherUser = User::factory()->create(['school_id' => $year->school_id]);
        $teacher = Teacher::factory()->create([
            'school_id'       => $year->school_id,
            'user_id'         => $teacherUser->id,
            'employee_number' => 'CLY-T-2026-0001',
            'first_name'      => 'John', 'last_name' => 'Smith',
            'email'           => $teacherUser->email,
            'hire_date'       => '2024-09-01',
            'status'          => 'active',
        ]);

        $response = $this->postJson("/api/v1/classes/{$class->id}/subjects", [
            'subject_id'  => $subject->id,
            'teacher_id'  => $teacher->id,
            'coefficient' => 4,
        ]);
        $response->assertOk();

        $this->assertDatabaseHas('class_subject', [
            'class_id'    => $class->id,
            'subject_id'  => $subject->id,
            'teacher_id'  => $teacher->id,
            'coefficient' => 4,
        ]);
    }

    public function test_class_detail_student_count_always_matches_returned_roster(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $year = AcademicYear::query()->where('status', AcademicYear::STATUS_ACTIVE)->firstOrFail();
        $class = SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'education_system' => 'secondary_general',
            'name' => 'Form 2 Roster',
            'grade_level' => 'Form 2',
            'cycle' => 'first_cycle',
            'language' => 'en',
            'capacity' => 40,
            'is_active' => true,
        ]);
        Student::factory()->count(10)->create([
            'school_id' => $this->admin->school_id,
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
        ]);

        $this->getJson("/api/v1/classes/{$class->id}")
            ->assertOk()
            ->assertJsonPath('data.students_count', 10)
            ->assertJsonCount(10, 'data.students');
    }
}
