<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\TenantPasswordResetNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        $this->assertDatabaseHas('tenant_password_reset_challenges', ['user_id' => $user->id]);
    }

    public function test_response_is_identical_for_unknown_email(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/forgot-password', ['email' => 'ghost@example.test'])
            ->assertOk()->assertJsonPath('success', true);
        Notification::assertNothingSent();
    }

    public function test_invalid_email_returns_422(): void
    {
        $this->postJson('/api/v1/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
