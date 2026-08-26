<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\AdminLoginOtpNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class PlatformOwnerLoginOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_owner_can_complete_email_otp_login(): void
    {
        config()->set('login_security.admin_email_otp_enabled', true);
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        Notification::fake();

        $owner = User::factory()->platformAdministrator()->create([
            'email' => 'owner@clyvero.test',
            'password' => Hash::make('OwnerSecure2026!'),
        ]);

        $login = $this->postJson('/api/v1/login', [
            'email' => 'owner@clyvero.test',
            'password' => 'OwnerSecure2026!',
        ])->assertOk()->assertJsonPath('data.requires_otp', true);

        $code = null;
        Notification::assertSentTo(
            $owner,
            AdminLoginOtpNotification::class,
            function (AdminLoginOtpNotification $notification) use (&$code): bool {
                $code = $notification->code;
                return true;
            },
        );

        $this->postJson('/api/v1/login/verify-otp', [
            'challenge_id' => $login->json('data.challenge_id'),
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('data.requires_otp', false)
            ->assertJsonPath('data.user.email', 'owner@clyvero.test')
            ->assertJsonPath('data.user.role.name', 'super_administrator');

        $this->assertSame(1, $owner->tokens()->count());
    }
}
