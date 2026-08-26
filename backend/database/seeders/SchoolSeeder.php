<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the single School row from .env values.
 *
 * Also creates a starter "active" academic year covering the current
 * Cameroonian school year (roughly September → June). The administrator
 * can edit/rename it after first login.
 *
 * Source: SRS §1 (single-school architecture).
 */
class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::firstOrCreate(
            ['email' => env('SCHOOL_EMAIL', 'school@example.test')],
            [
                // Default login code; administrators can change it later in Settings.
                'slug'        => env('SCHOOL_SLUG', Str::slug(env('SCHOOL_NAME', 'Demo School'))),
                'school_code' => env('SCHOOL_CODE', 'CLY-000001'),
                'school_name' => env('SCHOOL_NAME', 'Demo School'),
                'slogan'      => env('SCHOOL_SLOGAN'),
                'phone'       => env('SCHOOL_PHONE'),
                'address'     => env('SCHOOL_ADDRESS'),
                'website'     => env('SCHOOL_WEBSITE'),
                'city'        => env('SCHOOL_CITY'),
                'country'     => env('SCHOOL_COUNTRY'),
            ],
        );

        // Compute the current academic year window (Sep N → Jun N+1 for
        // the southern-cycle Cameroonian academic calendar).
        $startYear = (int) env('INITIAL_ACADEMIC_YEAR_START', 2026);
        $endYear   = $startYear + 1;

        $academicYear = AcademicYear::firstOrCreate(
            [
                'school_id' => $school->id,
                'title'     => "{$startYear}/{$endYear}",
            ],
            [
                'start_date' => "{$startYear}-09-01",
                'end_date'   => "{$endYear}-06-30",
                'status'     => AcademicYear::STATUS_ACTIVE,
            ],
        );

        $school->update(['current_academic_year_id' => $academicYear->id]);
    }
}
