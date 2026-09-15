<?php

declare(strict_types=1);

namespace Tests\Feature\Student;

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class StudentApiTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private AcademicYear $year;
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->school = School::factory()->create();
        $this->year = AcademicYear::factory()->create([
            'school_id' => $this->school->id,
            'status' => AcademicYear::STATUS_ACTIVE,
        ]);
        $this->class = SchoolClass::query()->create([
            'academic_year_id' => $this->year->id,
            'education_system' => 'secondary_general',
            'name' => 'Form 1',
            'grade_level' => 'Form 1',
            'cycle' => 'first_cycle',
            'language' => 'en',
            'capacity' => 40,
            'is_active' => true,
        ]);
        FeeStructure::query()->create([
            'academic_year_id' => $this->year->id,
            'name' => 'School fees',
            'category' => 'tuition',
            'amount' => 108000,
            'frequency' => 'annual',
            'is_required' => true,
        ]);
    }

    private function actingAsAdministrator(): User
    {
        $user = User::factory()->asRole(UserRole::Administrator)->create(['school_id' => $this->school->id]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function actingAsAssignedTeacher(): User
    {
        $user = User::factory()->asRole(UserRole::Teacher)->create(['school_id' => $this->school->id]);
        $teacher = Teacher::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $this->class->update(['form_master_id' => $teacher->id]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function actingAsSecretary(): User
    {
        $user = User::factory()->asRole(UserRole::Secretary)->create(['school_id' => $this->school->id]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /** @return array<string, mixed> */
    private function registrationPayload(string $matricule = 'MEC-2026-0001'): array
    {
        $key = strtolower(str_replace(['/', '.', '_'], '-', $matricule));

        return [
            'admission_number' => $matricule,
            'first_name' => 'Junior',
            'last_name' => 'Tah',
            'gender' => 'male',
            'date_of_birth' => '2010-05-20',
            'enrollment_date' => now()->toDateString(),
            'nationality' => 'Cameroonian',
            'guardian_mode' => 'new',
            'parent_first_name' => 'Mama',
            'parent_last_name' => 'Tah',
            'parent_gender' => 'female',
            'parent_email' => "parent-{$key}@example.test",
            'parent_phone' => '+237699112233',
            'parent_relationship' => 'mother',
            'create_parent_account' => true,
            'class_id' => $this->class->id,
            'academic_year_id' => $this->year->id,
            'cycle' => 'secondary_general',
            'initial_payment' => 0,
        ];
    }

    private function student(array $attributes = []): Student
    {
        return Student::factory()->create($attributes + [
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
        ]);
    }

    public function test_administrator_can_list_students(): void
    {
        $this->actingAsAdministrator();
        Student::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
        ]);

        $this->getJson('/api/v1/students')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'admission_number', 'full_name', 'status']]]);
    }

    public function test_list_excludes_archived_by_default_and_can_include_them(): void
    {
        $this->actingAsAdministrator();
        $this->student();
        $this->student();
        $this->student(['status' => StudentStatus::Archived->value, 'deleted_at' => now()]);
        $this->getJson('/api/v1/students')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/students?include_archived=1')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_list_can_be_searched_and_paginated(): void
    {
        $this->actingAsAdministrator();
        $this->student([
            'first_name' => 'Junior',
            'last_name' => 'Tah',
            'middle_name' => null,
            'email' => 'junior.tah@example.test',
        ]);
        $this->student([
            'first_name' => 'Marie',
            'last_name' => 'Ngono',
            'middle_name' => null,
            'email' => 'marie.ngono@example.test',
        ]);
        $this->getJson('/api/v1/students?q=Junior')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Junior');
        $this->getJson('/api/v1/students?q=J')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Junior');

        Student::factory()->count(13)->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
        ]);
        $this->getJson('/api/v1/students?per_page=10')->assertOk()
            ->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 15);
    }

    public function test_list_can_find_students_by_full_name_and_class(): void
    {
        $this->actingAsAdministrator();
        $student = $this->student([
            'first_name' => 'Junior',
            'last_name' => 'Tah',
            'middle_name' => null,
        ]);

        $this->getJson('/api/v1/students?q=Junior%20Tah')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $student->id)
            ->assertJsonPath('data.0.class.name', 'Form 1');

        $this->getJson('/api/v1/students?q=Form%201')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $student->id);
    }

    public function test_list_rejects_unsafe_sorting_and_oversized_pages(): void
    {
        $this->actingAsAdministrator();

        $this->getJson('/api/v1/students?sort=not_a_column')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');

        $this->getJson('/api/v1/students?per_page=1000000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/students')->assertUnauthorized()->assertJsonPath('error_code', 'unauthenticated');
    }

    public function test_teacher_cannot_register_a_student_even_when_assigned_to_the_class(): void
    {
        $this->actingAsAssignedTeacher();
        $this->postJson('/api/v1/students', $this->registrationPayload('MEC-2026-T001'))
            ->assertForbidden();
        $this->assertDatabaseMissing('students', ['admission_number' => 'MEC-2026-T001']);
    }

    public function test_administrator_can_view_a_student_and_unknown_id_returns_404(): void
    {
        $this->actingAsAdministrator();
        $student = $this->student();
        $this->getJson("/api/v1/students/{$student->id}")->assertOk()->assertJsonPath('data.id', $student->id);
        $this->getJson('/api/v1/students/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_administrator_can_register_a_student_with_manual_matricule(): void
    {
        $this->actingAsAdministrator();
        $this->postJson('/api/v1/students', $this->registrationPayload('MEC-2026-0042'))
            ->assertCreated()
            ->assertJsonPath('data.admission_number', 'MEC-2026-0042')
            ->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('students', [
            'school_id' => $this->school->id,
            'admission_number' => 'MEC-2026-0042',
            'official_matricule' => 'MEC-2026-0042',
        ]);
    }

    public function test_secretary_can_register_and_selected_class_controls_academic_pathway(): void
    {
        $this->actingAsSecretary();
        $payload = $this->registrationPayload('SEC-2026-0001');
        // A stale or manipulated browser value must never override the class.
        $payload['cycle'] = 'secondary_technical';
        $payload['speciality'] = 'EE';

        $this->postJson('/api/v1/students', $payload)
            ->assertCreated()
            ->assertJsonPath('data.admission_number', 'SEC-2026-0001')
            ->assertJsonPath('data.cycle', 'secondary_general');

        $this->assertDatabaseHas('students', [
            'school_id' => $this->school->id,
            'admission_number' => 'SEC-2026-0001',
            'class_id' => $this->class->id,
            'cycle' => 'secondary_general',
            'speciality' => null,
        ]);
    }

    public function test_manual_matricule_is_required_and_unique_per_school(): void
    {
        $this->actingAsAdministrator();
        $missing = $this->registrationPayload('MEC-2026-0043');
        unset($missing['admission_number']);
        $this->postJson('/api/v1/students', $missing)
            ->assertUnprocessable()->assertJsonValidationErrors(['admission_number']);

        $this->postJson('/api/v1/students', $this->registrationPayload('MEC-2026-0044'))->assertCreated();
        $duplicate = $this->registrationPayload('MEC-2026-0044');
        $duplicate['parent_email'] = 'different-parent@example.test';
        $this->postJson('/api/v1/students', $duplicate)
            ->assertUnprocessable()->assertJsonValidationErrors(['admission_number']);
    }

    public function test_store_validates_birth_date_and_duplicate_email(): void
    {
        $this->actingAsAdministrator();
        $future = $this->registrationPayload('MEC-2026-0045');
        $future['date_of_birth'] = now()->addYear()->toDateString();
        $this->postJson('/api/v1/students', $future)
            ->assertUnprocessable()->assertJsonValidationErrors(['date_of_birth']);

        $this->student(['email' => 'taken@example.test']);
        $duplicate = $this->registrationPayload('MEC-2026-0046');
        $duplicate['email'] = 'taken@example.test';
        $this->postJson('/api/v1/students', $duplicate)
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_personal_detail_update_does_not_require_fee_reconfiguration(): void
    {
        $this->actingAsAdministrator();
        $student = $this->student(['first_name' => 'Junior']);
        FeeStructure::query()->delete();

        $this->patchJson("/api/v1/students/{$student->id}", [
            'first_name' => 'Junior Updated',
            'phone' => '+237699000111',
        ])->assertOk()
            ->assertJsonPath('data.first_name', 'Junior Updated')
            ->assertJsonPath('data.phone', '+237699000111');
    }

    public function test_administrator_can_archive_and_restore_a_student(): void
    {
        $this->actingAsAdministrator();
        $student = $this->student();
        $this->deleteJson("/api/v1/students/{$student->id}")
            ->assertOk()->assertJsonPath('data.status', StudentStatus::Archived->value);
        $this->assertSoftDeleted('students', ['id' => $student->id]);
        $this->postJson("/api/v1/students/{$student->id}/restore")
            ->assertOk()->assertJsonPath('data.status', StudentStatus::Active->value);
    }

    public function test_photo_upload_stores_normalized_image(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Host PHP lacks GD; the production Sail image includes image processing support.');
        }
        Storage::fake('public');
        $this->actingAsAdministrator();
        $student = $this->student();
        $this->postJson("/api/v1/students/{$student->id}/photo", [
            'photo' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        ])->assertOk();
        $stored = $student->fresh()->photo;
        $this->assertNotNull($stored);
        $this->assertStringStartsWith('students/', $stored);
        Storage::disk('public')->assertExists($stored);
    }

    public function test_photo_upload_rejects_non_image_files(): void
    {
        $this->actingAsAdministrator();
        $student = $this->student();
        $this->postJson("/api/v1/students/{$student->id}/photo", [
            'photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['photo']);
    }

    public function test_statistics_return_current_school_counts(): void
    {
        $this->actingAsAdministrator();
        Student::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
        ]);
        $this->student(['status' => StudentStatus::Archived->value, 'deleted_at' => now()]);
        $this->getJson('/api/v1/students/statistics')->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.archived', 1);
    }
}
