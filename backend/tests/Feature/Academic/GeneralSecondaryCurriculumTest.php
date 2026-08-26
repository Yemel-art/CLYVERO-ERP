<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use App\Services\Academic\DefaultSecondaryCurriculum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class GeneralSecondaryCurriculumTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_catalogue_is_idempotent_filterable_and_supports_second_cycle_streams(): void
    {
        $this->seed();
        $school = School::factory()->create();
        $year = AcademicYear::factory()->create([
            'school_id' => $school->id,
            'title' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::STATUS_ACTIVE,
        ]);
        $school->update(['current_academic_year_id' => $year->id]);
        $admin = User::factory()->asRole(UserRole::Administrator)->create(['school_id' => $school->id]);
        Sanctum::actingAs($admin, ['*']);

        $curriculum = app(DefaultSecondaryCurriculum::class);
        $first = $curriculum->provision($school);
        $second = $curriculum->provision($school);
        $this->assertSame(15, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(15, Subject::query()->count());
        $this->assertSame(15, Subject::query()->distinct('color')->count('color'));

        Subject::query()->create([
            'school_id' => $school->id,
            'name' => 'Technical Drawing Only',
            'code' => 'TDO',
            'education_system' => 'secondary_technical',
            'coefficient' => 2,
            'color' => '#123456',
            'is_active' => true,
        ]);

        $subjects = $this->getJson('/api/v1/subjects?education_system=secondary_general&per_page=100')
            ->assertOk()
            ->json('data');
        $codes = collect($subjects)->pluck('code');
        $this->assertTrue($codes->contains('MATH'));
        $this->assertFalse($codes->contains('TDO'));

        $this->postJson('/api/v1/classes', [
            'academic_year_id' => $year->id,
            'education_system' => 'secondary_general',
            'speciality' => 'A',
            'cycle' => 'second_cycle',
            'grade_level' => '2nde',
            'language' => 'fr',
            'capacity' => 40,
        ])->assertCreated()->assertJsonPath('data.speciality', 'A');
    }
}
