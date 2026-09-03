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

    public function test_administrator_can_customize_only_their_school_student_id_card(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $admin = User::factory()->asRole(UserRole::Administrator)->create([
            'school_id' => $school->id,
        ]);
        Sanctum::actingAs($admin, ['*']);

        $settings = [
            'background_color' => '#FFF8F0',
            'border_color' => '#7C2D12',
            'accent_color' => '#C2410C',
            'text_color' => '#1C1917',
            'border_width' => 4,
            'corner_style' => 'rounded',
            'spacing' => 'compact',
            'header_height' => 17,
            'header_image_width' => 90,
            'year_gap' => 2,
            'font_scale' => 95,
            'photo_size' => 'standard',
            'show_title' => true,
            'title_fr' => 'Carte scolaire',
            'title_en' => 'Student Card',
            'show_motto' => true,
            'show_cameroon_flag' => true,
            'flag_size' => 10,
            'show_stamp' => true,
            'stamp_label' => 'School stamp',
            'show_signature' => true,
            'signature_label' => 'Principal',
        ];

        $this->patchJson('/api/v1/settings', ['student_id_card_settings' => $settings])
            ->assertOk()
            ->assertJsonPath('data.student_id_card_settings.border_color', '#7C2D12')
            ->assertJsonPath('data.student_id_card_settings.spacing', 'compact');

        $this->assertEquals($settings, $school->fresh()->student_id_card_settings);
        $this->assertNull($otherSchool->fresh()->student_id_card_settings);

        $this->patchJson('/api/v1/settings', [
            'student_id_card_settings' => [...$settings, 'border_width' => 8],
        ])->assertUnprocessable()->assertJsonValidationErrors(['student_id_card_settings.border_width']);
    }
}
