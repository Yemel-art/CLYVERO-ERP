<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiSchoolIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_email_can_log_in_to_two_schools_using_school_code(): void
    {
        $this->seed();
        $role = Role::where('name', UserRole::Administrator->value)->firstOrFail();
        $schoolA = School::factory()->create(['slug' => 'alpha-school']);
        $schoolB = School::factory()->create(['slug' => 'beta-school']);

        foreach ([$schoolA, $schoolB] as $school) {
            User::create([
                'school_id' => $school->id,
                'role_id' => $role->id,
                'first_name' => $school->slug,
                'last_name' => 'Admin',
                'email' => 'shared@example.test',
                'password' => Hash::make('SharedPassword2026!'),
                'is_active' => true,
            ]);
        }

        $this->postJson('/api/v1/login', [
            'school_slug' => 'beta-school',
            'email' => 'shared@example.test',
            'password' => 'SharedPassword2026!',
        ])->assertOk()->assertJsonPath('data.user.school.slug', 'beta-school');
    }

    public function test_school_users_cannot_list_or_route_bind_another_schools_students(): void
    {
        $this->seed();
        $role = Role::where('name', UserRole::Administrator->value)->firstOrFail();
        $schoolA = School::query()->firstOrFail();
        $schoolB = School::factory()->create(['slug' => 'isolated-school']);
        $adminA = User::factory()->create(['school_id' => $schoolA->id, 'role_id' => $role->id]);
        $yearA = AcademicYear::where('school_id', $schoolA->id)->firstOrFail();
        $yearB = AcademicYear::factory()->create([
            'school_id' => $schoolB->id,
            'title' => '2026/2027',
            'status' => AcademicYear::STATUS_ACTIVE,
        ]);
        $studentA = Student::factory()->create(['school_id' => $schoolA->id, 'academic_year_id' => $yearA->id]);
        $studentB = Student::factory()->create(['school_id' => $schoolB->id, 'academic_year_id' => $yearB->id]);

        Sanctum::actingAs($adminA, ['*']);

        $this->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $studentA->id);
        $this->getJson("/api/v1/students/{$studentB->id}")->assertNotFound();
        $this->getJson("/api/v1/students/{$studentB->id}/academic-history")->assertNotFound();
        $this->getJson("/api/v1/students/{$studentA->id}/academic-history")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
