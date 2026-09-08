<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AdminLoginOtpNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('login_security.admin_email_otp_enabled', true);
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_administrator_login_remains_available_while_email_otp_is_disabled(): void
    {
        config()->set('login_security.admin_email_otp_enabled', false);
        Notification::fake();

        $user = User::factory()->asRole(UserRole::Administrator)->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('AdminSecure2026!'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'admin@example.test',
            'password' => 'AdminSecure2026!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.requires_otp', false)
            ->assertJsonPath('data.user.email', 'admin@example.test')
            ->assertJsonPath('data.token_type', 'Bearer');

        Notification::assertNothingSent();
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_administrator_must_verify_emailed_otp_before_a_token_is_issued(): void
    {
        Notification::fake();
        $user = User::factory()->asRole(UserRole::Administrator)->create([
            'email'    => 'admin@example.test',
            'password' => Hash::make('TheLaureates2026!'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'admin@example.test',
            'password' => 'TheLaureates2026!',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'requires_otp',
                    'challenge_id',
                    'masked_email',
                    'expires_in',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requires_otp', true);

        $this->assertNull($user->fresh()->last_login_at);
        $this->assertSame(0, $user->tokens()->count());

        $code = null;
        Notification::assertSentTo(
            $user,
            AdminLoginOtpNotification::class,
            function (AdminLoginOtpNotification $notification) use (&$code): bool {
                $code = $notification->code;
                return preg_match('/^\d{6}$/', $code) === 1;
            },
        );

        $verification = $this->postJson('/api/v1/login/verify-otp', [
            'challenge_id' => $response->json('data.challenge_id'),
            'code' => $code,
        ]);

        $verification
            ->assertOk()
            ->assertJsonPath('data.requires_otp', false)
            ->assertJsonPath('data.user.email', 'admin@example.test')
            ->assertJsonPath('data.user.role.name', 'administrator')
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_non_administrator_login_remains_unchanged(): void
    {
        $user = User::factory()->asRole(UserRole::Teacher)->create([
            'email' => 'teacher@example.test',
            'password' => Hash::make('TeacherSecure2026!'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'teacher@example.test',
            'password' => 'TeacherSecure2026!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.requires_otp', false)
            ->assertJsonPath('data.user.role.name', 'teacher')
            ->assertJsonPath('data.token_type', 'Bearer');
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_administrator_otp_is_single_use(): void
    {
        Notification::fake();
        $user = User::factory()->asRole(UserRole::Administrator)->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('AdminSecure2026!'),
        ]);

        $login = $this->postJson('/api/v1/login', [
            'email' => 'admin@example.test',
            'password' => 'AdminSecure2026!',
        ])->assertOk();

        $code = null;
        Notification::assertSentTo($user, AdminLoginOtpNotification::class, function ($notification) use (&$code): bool {
            $code = $notification->code;
            return true;
        });

        $payload = ['challenge_id' => $login->json('data.challenge_id'), 'code' => $code];
        $this->postJson('/api/v1/login/verify-otp', $payload)->assertOk();
        $this->postJson('/api/v1/login/verify-otp', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'user@example.test',
            'password' => Hash::make('CorrectPass1234!'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'user@example.test',
            'password' => 'WrongPassword1234!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'invalid_credentials');
    }

    public function test_login_fails_when_user_does_not_exist(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email'    => 'nobody@example.test',
            'password' => 'AnyPassword1234!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'invalid_credentials');
    }

    public function test_login_fails_when_account_is_inactive(): void
    {
        User::factory()->inactive()->create([
            'email'    => 'inactive@example.test',
            'password' => Hash::make('CorrectPass1234!'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'inactive@example.test',
            'password' => 'CorrectPass1234!',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('error_code', 'account_inactive');
    }

    public function test_login_fails_when_account_is_locked(): void
    {
        User::factory()->locked()->create([
            'email'    => 'locked@example.test',
            'password' => Hash::make('CorrectPass1234!'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'locked@example.test',
            'password' => 'CorrectPass1234!',
        ]);

        $response
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'account_locked');
    }

    public function test_account_locks_after_five_failed_attempts(): void
    {
        User::factory()->create([
            'email'    => 'target@example.test',
            'password' => Hash::make('CorrectPass1234!'),
        ]);

        // Five wrong attempts. Use different IPs to bypass the
        // route-level throttle and isolate the in-app lockout logic.
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/v1/login', [
                    'email'    => 'target@example.test',
                    'password' => 'WrongPassword1234!',
                ]);
        }

        // Sixth attempt — even with correct password — should be locked.
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson('/api/v1/login', [
                'email'    => 'target@example.test',
                'password' => 'CorrectPass1234!',
            ]);

        $response
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'account_locked');
    }

    public function test_login_returns_422_when_email_missing(): void
    {
        $response = $this->postJson('/api/v1/login', ['password' => 'something']);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_returns_422_when_email_invalid(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email'    => 'not-an-email',
            'password' => 'something',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_email_is_normalized_before_lookup(): void
    {
        User::factory()->asRole(UserRole::Secretary)->create([
            'email'    => 'mixedcase@example.test',
            'password' => Hash::make('CorrectPass1234!'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => '  MixedCase@Example.TEST  ',
            'password' => 'CorrectPass1234!',
        ]);

        $response->assertOk()->assertJsonPath('data.user.email', 'mixedcase@example.test');
    }
}
