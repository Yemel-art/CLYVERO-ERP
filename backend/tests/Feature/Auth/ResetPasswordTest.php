<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\TenantPasswordResetNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_valid_tenant_token_resets_password_and_is_one_time(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'reset@example.test',
            'password' => Hash::make('OldPassword1234!'),
        ]);
        $token = $this->requestToken($user);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'school_slug' => $user->school->school_code,
            'password' => 'BrandNewPass2026!',
            'password_confirmation' => 'BrandNewPass2026!',
        ];
        $this->postJson('/api/v1/reset-password', $payload)
            ->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('BrandNewPass2026!', $user->fresh()->password));
        $this->postJson('/api/v1/reset-password', $payload)->assertUnprocessable();
    }

    public function test_existing_tokens_are_revoked_after_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'tokens@example.test']);
        $user->createToken('legacy');
        $token = $this->requestToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'school_slug' => $user->school->school_code,
            'password' => 'BrandNewPass2026!',
            'password_confirmation' => 'BrandNewPass2026!',
        ])->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'invalid@example.test']);
        $this->postJson('/api/v1/reset-password', [
            'token' => str_repeat('x', 64),
            'email' => $user->email,
            'school_slug' => $user->school->school_code,
            'password' => 'BrandNewPass2026!',
            'password_confirmation' => 'BrandNewPass2026!',
        ])->assertUnprocessable();
    }

    public function test_password_must_meet_strength_policy(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'strength@example.test']);
        $token = $this->requestToken($user);
        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'school_slug' => $user->school->school_code,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_password_confirmation_must_match(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'confirm@example.test']);
        $token = $this->requestToken($user);
        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'school_slug' => $user->school->school_code,
            'password' => 'BrandNewPass2026!',
            'password_confirmation' => 'DifferentPass2026!',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_current_password_cannot_be_reused_during_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'reuse@example.test',
            'password' => Hash::make('ExistingPass2026!'),
        ]);
        $token = $this->requestToken($user);
        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'school_slug' => $user->school->school_code,
            'password' => 'ExistingPass2026!',
            'password_confirmation' => 'ExistingPass2026!',
        ])->assertUnprocessable()->assertJsonPath('error_code', 'password_reset_failed');
        $this->assertTrue(Hash::check('ExistingPass2026!', $user->fresh()->password));
    }

    private function requestToken(User $user): string
    {
        $this->postJson('/api/v1/forgot-password', [
            'email' => $user->email,
            'school_slug' => $user->school->school_code,
        ])->assertOk();

        /** @var TenantPasswordResetNotification $notification */
        $notification = Notification::sent($user, TenantPasswordResetNotification::class)->last();
        parse_str((string) parse_url($notification->toMail($user)->actionUrl, PHP_URL_QUERY), $query);

        return (string) ($query['token'] ?? '');
    }
}
