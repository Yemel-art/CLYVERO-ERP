<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_authenticated_user_can_change_password_and_all_tokens_are_revoked(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldSecurePass2026!'),
        ]);
        $currentToken = $user->createToken('current-session')->plainTextToken;
        $user->createToken('other-session');

        $response = $this->withToken($currentToken)->putJson('/api/v1/me/password', [
            'current_password' => 'OldSecurePass2026!',
            'password' => 'NewSecurePass2027!',
            'password_confirmation' => 'NewSecurePass2027!',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertTrue(Hash::check('NewSecurePass2027!', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'activity' => 'password_changed',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'auth',
            'action' => AuditAction::PasswordChanged->value,
        ]);
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldSecurePass2026!'),
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'WrongSecurePass2026!',
            'password' => 'NewSecurePass2027!',
            'password_confirmation' => 'NewSecurePass2027!',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('OldSecurePass2026!', $user->fresh()->password));
    }

    public function test_new_password_must_be_strong_confirmed_and_different(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldSecurePass2026!'),
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'OldSecurePass2026!',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'OldSecurePass2026!',
            'password' => 'NewSecurePass2027!',
            'password_confirmation' => 'DifferentPass2027!',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'OldSecurePass2026!',
            'password' => 'OldSecurePass2026!',
            'password_confirmation' => 'OldSecurePass2026!',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_unauthenticated_user_cannot_change_password(): void
    {
        $this->putJson('/api/v1/me/password', [
            'current_password' => 'OldSecurePass2026!',
            'password' => 'NewSecurePass2027!',
            'password_confirmation' => 'NewSecurePass2027!',
        ])->assertUnauthorized();
    }
}
