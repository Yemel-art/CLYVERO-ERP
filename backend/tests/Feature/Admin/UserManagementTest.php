<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_admin_can_create_a_secretary_with_a_school_scoped_email(): void
    {
        $admin = User::factory()->asRole(UserRole::Administrator)->create();
        $otherSchool = School::factory()->create();
        User::factory()->asRole(UserRole::Secretary)->create([
            'school_id' => $otherSchool->id,
            'email' => 'secretary@example.test',
        ]);
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/users', [
            'first_name' => '  Alice ',
            'last_name' => ' Secretary  ',
            'email' => ' Secretary@Example.Test ',
            'phone' => '+237 600 000 000',
            'password' => 'Secure@School26',
            'role' => UserRole::Secretary->value,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.email', 'secretary@example.test');

        $secretaryRole = Role::where('name', UserRole::Secretary->value)->firstOrFail();
        $this->assertDatabaseHas('users', [
            'school_id' => $admin->school_id,
            'role_id' => $secretaryRole->id,
            'first_name' => 'Alice',
            'last_name' => 'Secretary',
            'email' => 'secretary@example.test',
            'is_active' => true,
        ]);
    }

    public function test_secretary_creation_returns_the_exact_password_error(): void
    {
        $admin = User::factory()->asRole(UserRole::Administrator)->create();
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/users', [
            'first_name' => 'Alice',
            'last_name' => 'Secretary',
            'email' => 'alice.secretary@example.test',
            'password' => 'alllowercase',
            'role' => UserRole::Secretary->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
