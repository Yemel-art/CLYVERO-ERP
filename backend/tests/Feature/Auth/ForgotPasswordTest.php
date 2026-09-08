<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Notifications\TenantPasswordResetNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

final class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_existing_user_receives_tenant_bound_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'real@example.test']);

        $this->postJson('/api/v1/forgot-password', [
            'email' => $user->email,
            'school_slug' => $user->school->school_code,
        ])->assertOk()->assertJsonPath('success', true);

        Notification::assertSentTo($user, TenantPasswordResetNotification::class);
        $this->assertInstanceOf(ShouldQueue::class, new TenantPasswordResetNotification(str_repeat('x', 64)));
        $this->assertDatabaseHas('tenant_password_reset_challenges', ['user_id' => $user->id]);
    }

    public function test_response_is_identical_for_unknown_email(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/forgot-password', ['email' => 'ghost@example.test'])
            ->assertOk()->assertJsonPath('success', true);
        Notification::assertNothingSent();
    }

    public function test_platform_owner_receives_owner_scoped_reset_link_without_a_school_code(): void
    {
        Notification::fake();
        $role = Role::query()->where('name', UserRole::SuperAdministrator->value)->firstOrFail();
        $owner = User::factory()->create([
            'school_id' => null,
            'role_id' => $role->id,
            'email' => 'owner@example.test',
        ]);

        $this->postJson('/api/v1/forgot-password', [
            'email' => $owner->email,
            'account_scope' => 'platform',
        ])->assertOk()->assertJsonPath('success', true);

        Notification::assertSentTo($owner, TenantPasswordResetNotification::class, function ($notification) use ($owner): bool {
            $url = $notification->toMail($owner)->actionUrl;

            return str_contains($url, 'scope=platform') && ! str_contains($url, 'school=');
        });
        $this->assertDatabaseHas('tenant_password_reset_challenges', ['user_id' => $owner->id]);
    }

    public function test_platform_scope_never_sends_a_reset_to_a_school_user(): void
    {
        Notification::fake();
        $schoolUser = User::factory()->create(['email' => 'principal@example.test']);

        $this->postJson('/api/v1/forgot-password', [
            'email' => $schoolUser->email,
            'account_scope' => 'platform',
        ])->assertOk()->assertJsonPath('success', true);

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('tenant_password_reset_challenges', ['user_id' => $schoolUser->id]);
    }

    public function test_invalid_email_returns_422(): void
    {
        $this->postJson('/api/v1/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
