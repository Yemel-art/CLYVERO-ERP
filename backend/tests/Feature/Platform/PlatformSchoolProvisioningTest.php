<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PlatformSchoolProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_provision_an_isolated_school_and_initial_year(): void
    {
        Storage::fake('public');
        $this->seed();
        $super = User::factory()->platformAdministrator()->create();
        Sanctum::actingAs($super, ['*']);

        $response = $this->postJson('/api/v1/platform/schools', [
            'school_name' => 'Clyvero Demonstration College',
            'email' => 'office@demo-college.test',
            'default_locale' => 'fr',
            'education_systems' => ['secondary_general', 'secondary_technical'],
            'administrator_first_name' => 'Divine',
            'administrator_last_name' => 'Administrator',
            'administrator_email' => 'admin@demo-college.test',
            'administrator_password' => 'Strong!Platform2027',
        ])->assertCreated();

        $schoolId = $response->json('data.id');
        $code = $response->json('data.school_code');
        $startYear = now()->month >= 7 ? now()->year : now()->year - 1;
        $this->assertMatchesRegularExpression('/^CLY-\d{6}$/', $code);
        $this->assertDatabaseHas('schools', [
            'id' => $schoolId,
            'school_code' => $code,
        ]);
        $this->assertMatchesRegularExpression(
            '/^SCH-[A-F0-9]{8}$/',
            (string) School::withoutGlobalScopes()->findOrFail($schoolId)->access_code,
        );
        $this->assertDatabaseHas('academic_years', [
            'school_id' => $schoolId,
            'title' => $startYear.'-'.($startYear + 1),
            'status' => AcademicYear::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('terms', [
            'academic_year_id' => School::withoutGlobalScopes()->findOrFail($schoolId)->current_academic_year_id,
            'sequence' => 1, 'status' => 'active',
        ]);
        $this->assertDatabaseCount('terms', 3);
        $this->assertDatabaseHas('users', [
            'school_id' => $schoolId, 'email' => 'admin@demo-college.test',
        ]);
        $this->assertDatabaseHas('subjects', [
            'school_id' => $schoolId, 'code' => 'MATH', 'education_system' => 'both',
        ]);
        $this->assertDatabaseHas('subjects', [
            'school_id' => $schoolId, 'code' => 'ECON', 'education_system' => 'secondary_general',
        ]);

        $overview = $this->getJson('/api/v1/platform/overview')
            ->assertOk()
            ->json('data');
        $this->assertGreaterThanOrEqual(1, $overview['schools_total']);
        $this->assertGreaterThanOrEqual(1, $overview['school_administrators']);

        $this->patchJson("/api/v1/platform/schools/{$schoolId}", [
            'school_name' => 'Clyvero Demonstration College Updated',
            'school_code' => $code,
            'slug' => 'clyvero-demonstration-college-updated',
            'slogan' => 'Knowledge and integrity',
            'email' => 'identity@demo-college.test',
            'default_locale' => 'fr',
            'education_systems' => ['secondary_general'],
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'document_header' => 'Official school document header',
            'document_footer' => 'Official school document footer',
            'principal_name' => 'Divine Principal',
            'principal_title' => 'Proviseur',
        ])->assertOk()
            ->assertJsonPath('data.school_name', 'Clyvero Demonstration College Updated')
            ->assertJsonPath('data.document_header', 'Official school document header')
            ->assertJsonMissingPath('data.honor_roll_rules');
        $this->assertDatabaseHas('schools', [
            'id' => $schoolId,
            'school_name' => 'Clyvero Demonstration College Updated',
            'document_header' => 'Official school document header',
            'principal_name' => 'Divine Principal',
        ]);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nK0AAAAASUVORK5CYII=');
        $logoResponse = $this->post("/api/v1/platform/schools/{$schoolId}/logo", [
            'logo' => UploadedFile::fake()->createWithContent('school-logo.png', $png),
            'kind' => 'primary',
        ], ['Accept' => 'application/json'])->assertOk();
        $logoPath = School::withoutGlobalScopes()->findOrFail($schoolId)->logo;
        $this->assertNotNull($logoPath);
        Storage::disk('public')->assertExists($logoPath);
        $this->assertNotNull($logoResponse->json('data.logo_url'));

        $headerResponse = $this->post("/api/v1/platform/schools/{$schoolId}/logo", [
            'logo' => UploadedFile::fake()->createWithContent('official-header.png', $png),
            'kind' => 'document_header',
        ], ['Accept' => 'application/json'])->assertOk();
        $headerPath = School::withoutGlobalScopes()->findOrFail($schoolId)->document_header_image;
        $this->assertNotNull($headerPath);
        Storage::disk('public')->assertExists($headerPath);
        $this->assertNotNull($headerResponse->json('data.document_header_image_url'));

        $this->getJson('/api/v1/students')->assertForbidden();
        $this->withHeader('X-School-Code', $code)->getJson('/api/v1/students')->assertForbidden();

        $schoolAdmin = User::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('email', 'admin@demo-college.test')
            ->firstOrFail();
        Sanctum::actingAs($schoolAdmin, ['*']);
        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.school.school_code', $code)
            ->assertJsonPath(
                'data.school.logo_url',
                fn (mixed $value): bool => is_string($value) && str_contains($value, '/api/v1/media/schools/'),
            );
        $this->getJson('/api/v1/students')->assertOk();

        Sanctum::actingAs($super, ['*']);
        $schoolAdmin->createToken('school-session');
        $this->getJson("/api/v1/platform/schools/{$schoolId}/administrator-credentials")
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@demo-college.test');
        $this->patchJson("/api/v1/platform/schools/{$schoolId}/administrator-credentials", [
            'first_name' => 'Divine',
            'last_name' => 'Principal',
            'email' => 'corrected.principal@demo-college.test',
            'administrator_password' => 'Corrected!Principal2027',
            'administrator_password_confirmation' => 'Corrected!Principal2027',
        ])->assertOk()
            ->assertJsonPath('data.email', 'corrected.principal@demo-college.test')
            ->assertJsonMissingPath('data.password');
        $schoolAdmin->refresh();
        $this->assertSame('corrected.principal@demo-college.test', $schoolAdmin->email);
        $this->assertTrue(Hash::check('Corrected!Principal2027', $schoolAdmin->password));
        $this->assertSame(0, $schoolAdmin->tokens()->count());

        $schoolAdmin->createToken('school-session-after-credential-change');
        $this->patchJson("/api/v1/platform/schools/{$schoolId}/status", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertSame(0, $schoolAdmin->tokens()->count());
        $this->getJson('/api/v1/students')->assertForbidden();
        $this->patchJson("/api/v1/platform/schools/{$schoolId}/status", ['is_active' => true])
            ->assertOk()->assertJsonPath('data.is_active', true);
        $this->getJson('/api/v1/students')->assertForbidden();
    }

    public function test_normal_school_administrator_cannot_use_platform_routes(): void
    {
        $this->seed();
        $school = School::factory()->create();
        $role = Role::query()->where('name', UserRole::Administrator->value)->firstOrFail();
        $admin = User::factory()->create(['school_id' => $school->id, 'role_id' => $role->id]);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/platform/schools')->assertForbidden();
    }
}
