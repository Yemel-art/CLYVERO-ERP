<?php

declare(strict_types=1);

namespace Tests\Feature\Parent;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\ParentGuardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ParentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $s = School::factory()->create();
        AcademicYear::factory()->create(['school_id' => $s->id, 'status' => AcademicYear::STATUS_ACTIVE]);
    }

    private function asAdmin(): User
    {
        $u = User::factory()->asRole(UserRole::Administrator)->create();
        Sanctum::actingAs($u, ['*']);
        return $u;
    }

    public function test_admin_can_list_parents(): void
    {
        $this->asAdmin();
        ParentGuardian::factory()->count(3)->create();
        $this->getJson('/api/v1/parents')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_admin_can_register_a_parent_with_user_account(): void
    {
        $this->asAdmin();
        $response = $this->postJson('/api/v1/parents', [
            'first_name' => 'Mama', 'last_name' => 'Tah',
            'gender'     => 'female',
            'email'      => 'mama.tah@example.test',
            'phone'      => '+237699112233',
        ]);
        $response->assertCreated()->assertJsonPath('data.email', 'mama.tah@example.test');
        $this->assertDatabaseHas('parents', ['email' => 'mama.tah@example.test']);
        $this->assertDatabaseHas('users', ['email' => 'mama.tah@example.test']);
    }

    public function test_can_attach_and_detach_children(): void
    {
        $this->asAdmin();
        $parent = ParentGuardian::factory()->create();
        $student = Student::factory()->create();

        $this->postJson("/api/v1/parents/{$parent->id}/children", [
            'student_id'   => $student->id,
            'relationship' => 'Mother',
            'is_primary'   => true,
        ])->assertOk()->assertJsonPath('data.children_count', 1);

        // Primary link must also set the legacy student.parent_id
        $this->assertDatabaseHas('students', ['id' => $student->id, 'parent_id' => $parent->id]);

        $this->deleteJson("/api/v1/parents/{$parent->id}/children/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.children_count', 0);

        $this->assertDatabaseHas('students', ['id' => $student->id, 'parent_id' => null]);
    }

    public function test_parent_users_can_only_see_their_own_record(): void
    {
        $parent  = ParentGuardian::factory()->create();
        $other   = ParentGuardian::factory()->create();

        // Create a User with Parent role linked to $parent
        $parentRole = \App\Models\Role::where('name', UserRole::Parent->value)->firstOrFail();
        $user = User::factory()->create(['role_id' => $parentRole->id]);
        $parent->update(['user_id' => $user->id]);
        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/v1/parents/{$parent->id}")->assertOk();
        $this->getJson("/api/v1/parents/{$other->id}")->assertForbidden();
    }

    public function test_archive_deactivates_user_account(): void
    {
        $this->asAdmin();
        $parentRole = \App\Models\Role::where('name', UserRole::Parent->value)->firstOrFail();
        $user = User::factory()->create(['role_id' => $parentRole->id, 'is_active' => true]);
        $parent = ParentGuardian::factory()->create(['user_id' => $user->id]);

        $this->deleteJson("/api/v1/parents/{$parent->id}")->assertOk();
        $this->assertSoftDeleted('parents', ['id' => $parent->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
    }
}
