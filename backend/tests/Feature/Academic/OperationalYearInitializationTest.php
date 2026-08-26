<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OperationalYearInitializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_test_dataset_can_be_replaced_by_the_first_operational_year(): void
    {
        $school = School::factory()->create(['school_code' => 'CLY-REHEARSAL']);
        $old = AcademicYear::factory()->create([
            'school_id' => $school->id,
            'title' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'status' => AcademicYear::STATUS_ACTIVE,
        ]);
        $target = AcademicYear::factory()->create([
            'school_id' => $school->id,
            'title' => '2026-2027',
            // Rehearse a legacy record whose title is correct but whose dates
            // differ from the normalized operational calendar.
            'start_date' => '2026-08-15',
            'end_date' => '2027-07-15',
            'status' => AcademicYear::STATUS_UPCOMING,
        ]);
        $school->update(['current_academic_year_id' => $old->id]);
        $student = Student::factory()->create([
            'school_id' => $school->id,
            'academic_year_id' => $old->id,
        ]);

        $this->artisan('clyvero:initialize-2026-2027', [
            '--school' => 'CLY-REHEARSAL',
            '--purge-test-students' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('academic_years', ['id' => $old->id]);
        $this->assertDatabaseHas('academic_years', [
            'id' => $target->id,
            'title' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('terms', [
            'academic_year_id' => $target->id, 'sequence' => 1, 'status' => 'active',
        ]);
        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'current_academic_year_id' => $target->id,
        ]);
    }
}
