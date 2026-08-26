<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class SchoolCodeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_administrator_can_view_and_change_own_school_code(): void
    {
        $school = School::factory()->create(['slug' => 'original-school']);
        $admin = User::factory()->asRole(UserRole::Administrator)->create([
            'school_id' => $school->id,
        ]);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.school.slug', 'original-school');

        $this->patchJson('/api/v1/settings', ['slug' => 'new-school-code'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-school-code');

        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'slug' => 'new-school-code',
        ]);
    }

    public function test_school_code_must_be_unique_and_url_safe(): void
    {
        School::factory()->create(['slug' => 'already-used']);
        $school = School::factory()->create(['slug' => 'current-school']);
        $admin = User::factory()->asRole(UserRole::Administrator)->create([
            'school_id' => $school->id,
        ]);
        Sanctum::actingAs($admin, ['*']);

        $this->patchJson('/api/v1/settings', ['slug' => 'already-used'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);

        $this->patchJson('/api/v1/settings', ['slug' => 'Invalid Code!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }
}
