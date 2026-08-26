<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class TenantPasswordResetIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_challenge_is_bound_to_one_user_and_school(): void
    {
        Notification::fake();
        $this->seed();
        $role = Role::query()->where('name', 'administrator')->firstOrFail();
        $schoolA = School::query()->firstOrFail();
        $schoolB = School::factory()->create();
        $email = 'shared-admin@example.test';
        $userA = User::factory()->create(['school_id' => $schoolA->id, 'role_id' => $role->id, 'email' => $email]);
        $userB = User::factory()->create(['school_id' => $schoolB->id, 'role_id' => $role->id, 'email' => $email]);

        $this->postJson('/api/v1/forgot-password', ['email' => $email, 'school_slug' => $schoolA->school_code])->assertOk();
        $this->assertDatabaseHas('tenant_password_reset_challenges', ['user_id' => $userA->id]);
        $this->assertDatabaseMissing('tenant_password_reset_challenges', ['user_id' => $userB->id]);
        $this->postJson('/api/v1/forgot-password', ['email' => $email])->assertOk();
        $this->assertSame(1, DB::table('tenant_password_reset_challenges')->count());
        $this->assertTrue(Hash::check('Test1234!Pass', $userB->fresh()->password));
    }
}
