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
            'account_scope' => 'platform',
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

    public function test_platform_scope_selects_owner_when_a_school_user_has_the_same_email(): void
    {
        config()->set('login_security.admin_email_otp_enabled', false);
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        User::factory()->create([
            'email' => 'shared@clyvero.test',
            'password' => Hash::make('SchoolPassword2026!'),
        ]);
        $owner = User::factory()->platformAdministrator()->create([
            'email' => 'shared@clyvero.test',
            'password' => Hash::make('OwnerPassword2026!'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'shared@clyvero.test',
            'password' => 'OwnerPassword2026!',
            'account_scope' => 'platform',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $owner->id)
            ->assertJsonPath('data.user.role.name', 'super_administrator');
    }
}
