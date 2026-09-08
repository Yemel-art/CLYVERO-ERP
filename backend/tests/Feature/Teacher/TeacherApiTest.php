<?php

declare(strict_types=1);

namespace Tests\Feature\Teacher;

use App\Enums\TeacherStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TeacherApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    private function asAdmin(): User
    {
        $u = User::factory()->asRole(UserRole::Administrator)->create();
        Sanctum::actingAs($u, ['*']);
        return $u;
    }

    public function test_admin_can_list_teachers(): void
    {
        $this->asAdmin();
        Teacher::factory()->count(3)->create();

        $this->getJson('/api/v1/teachers')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_register_a_teacher(): void
    {
        $this->asAdmin();

        $response = $this->postJson('/api/v1/teachers', [
            'first_name' => 'Marie', 'last_name' => 'Ngono',
            'gender'     => 'female', 'email' => 'marie.ngono@example.test',
            'hire_date'  => now()->subYear()->toDateString(),
            'qualification' => 'MSc Mathematics',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.teacher.email', 'marie.ngono@example.test');
        $this->assertDatabaseHas('teachers', ['email' => 'marie.ngono@example.test']);
        // A linked user account should have been created.
        $this->assertDatabaseHas('users', ['email' => 'marie.ngono@example.test']);
    }

    public function test_employee_number_is_generated_with_prefix(): void
    {
        $this->asAdmin();

        $response = $this->postJson('/api/v1/teachers', [
            'first_name' => 'A', 'last_name' => 'B',
            'gender' => 'male', 'email' => 'a@x.test',
            'hire_date' => now()->toDateString(),
        ]);

        $this->assertMatchesRegularExpression(
            '/^[A-Z0-9-]+-T-\d{4}-\d{4}$/',
            $response->json('data.teacher.employee_number'),
        );
    }

    public function test_registration_reuses_an_unlinked_teacher_login_account(): void
    {
        $admin = $this->asAdmin();
        $login = User::factory()->asRole(UserRole::Teacher)->create([
            'school_id' => $admin->school_id,
            'first_name' => 'Paul',
            'last_name' => 'Talla',
            'email' => 'paul.talla@example.test',
        ]);

        $response = $this->postJson('/api/v1/teachers', [
            'first_name' => 'Paul',
            'last_name' => 'Talla',
            'gender' => 'male',
            'email' => 'paul.talla@example.test',
            'hire_date' => now()->subMonth()->toDateString(),
            'create_user_account' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.teacher.user.id', $login->id)
            ->assertJsonPath('data.login_credentials', null);
        $this->assertDatabaseHas('teachers', [
            'school_id' => $admin->school_id,
            'user_id' => $login->id,
            'email' => 'paul.talla@example.test',
        ]);
    }

    public function test_teacher_profile_can_repair_a_safe_legacy_email_link(): void
    {
        $admin = $this->asAdmin();
        $login = User::factory()->asRole(UserRole::Teacher)->create([
            'school_id' => $admin->school_id,
            'email' => 'legacy.teacher@example.test',
        ]);
        $teacher = Teacher::factory()->create([
            'school_id' => $admin->school_id,
            'user_id' => null,
            'email' => 'legacy.teacher@example.test',
        ]);

        $this->assertSame($teacher->id, Teacher::resolveForUser($login)?->id);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'user_id' => $login->id]);
    }

    public function test_teacher_dashboard_returns_form_master_and_subject_assignments(): void
    {
        $admin = $this->asAdmin();
        $year = AcademicYear::factory()->create([
            'school_id' => $admin->school_id,
            'title' => '2026/2027',
            'status' => AcademicYear::STATUS_ACTIVE,
        ]);
        $login = User::factory()->asRole(UserRole::Teacher)->create([
            'school_id' => $admin->school_id,
            'email' => 'dashboard.teacher@example.test',
        ]);
        $teacher = Teacher::factory()->create([
            'school_id' => $admin->school_id,
            'user_id' => $login->id,
            'email' => $login->email,
        ]);
        $class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'form_master_id' => $teacher->id,
            'name' => 'Form 2 Electricity',
            'grade_level' => 'Form 2',
            'speciality' => 'Electrical Engineering',
            'cycle' => 'first_cycle',
            'language' => 'en',
            'capacity' => 40,
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_id' => $admin->school_id,
            'name' => 'Mathematics',
            'code' => 'MATH',
            'coefficient' => 4,
            'color' => '#2563eb',
            'is_active' => true,
        ]);
        $class->subjects()->attach($subject->id, [
            'id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'coefficient' => 4,
            'weekly_frequency' => 5,
        ]);

        Sanctum::actingAs($login, ['*']);

        $this->getJson('/api/v1/dashboard/teacher')
            ->assertOk()
            ->assertJsonPath('data.teacher.id', $teacher->id)
            ->assertJsonPath('data.form_master_classes.0.id', $class->id)
            ->assertJsonPath('data.teaching.0.class_id', $class->id)
            ->assertJsonPath('data.teaching.0.subject_id', $subject->id);

        $this->getJson("/api/v1/classes?academic_year_id={$year->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $class->id);
    }

    public function test_archive_and_restore(): void
    {
        $this->asAdmin();
        $teacher = Teacher::factory()->create();

        $this->deleteJson("/api/v1/teachers/{$teacher->id}")
            ->assertOk()
            ->assertJsonPath('data.status', TeacherStatus::Archived->value);

        $this->postJson("/api/v1/teachers/{$teacher->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.status', TeacherStatus::Active->value);
    }

    public function test_statistics(): void
    {
        $this->asAdmin();
        Teacher::factory()->count(2)->create();
        Teacher::factory()->archived()->count(1)->create();

        $this->getJson('/api/v1/teachers/statistics')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.archived', 1);
    }
}
