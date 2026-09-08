<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->asRole(UserRole::Teacher)->create([
            'first_name' => 'Junior',
            'last_name'  => 'Tah',
            'email'      => 'junior@example.test',
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.email', 'junior@example.test')
            ->assertJsonPath('data.full_name', 'Junior Tah')
            ->assertJsonPath('data.role.name', 'teacher')
            ->assertJsonStructure([
                'data' => [
                    'id', 'first_name', 'last_name', 'full_name',
                    'email', 'is_active',
                    'role' => ['id', 'name', 'display_name', 'permissions'],
                ],
            ]);
    }

    public function test_unauthenticated_request_to_me_is_rejected(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthenticated');
    }

    public function test_authenticated_user_can_change_login_email_with_current_password(): void
    {
        $user = User::factory()->asRole(UserRole::Administrator)->create([
            'email' => 'old@example.test',
            'password' => Hash::make('CurrentSecure2026!'),
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson('/api/v1/me', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => '  Real.Admin@Example.COM ',
            'phone' => $user->phone,
            'current_password' => 'CurrentSecure2026!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.email', 'real.admin@example.com');
        $this->assertSame('real.admin@example.com', $user->fresh()->email);
    }

    public function test_email_change_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->asRole(UserRole::Administrator)->create([
            'email' => 'old@example.test',
            'password' => Hash::make('CurrentSecure2026!'),
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->patchJson('/api/v1/me', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'new@example.com',
            'phone' => $user->phone,
            'current_password' => 'WrongSecure2026!',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $this->assertSame('old@example.test', $user->fresh()->email);
    }

    public function test_email_change_rejects_an_address_already_in_use(): void
    {
        $user = User::factory()->asRole(UserRole::Administrator)->create([
            'email' => 'old@example.test',
            'password' => Hash::make('CurrentSecure2026!'),
        ]);
        User::factory()->create(['email' => 'used@example.com']);
        Sanctum::actingAs($user, ['*']);

        $this->patchJson('/api/v1/me', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'used@example.com',
            'phone' => $user->phone,
            'current_password' => 'CurrentSecure2026!',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }
}
