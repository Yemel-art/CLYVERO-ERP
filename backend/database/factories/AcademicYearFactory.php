<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $year = (int) now()->year;
        return [
            'school_id'  => School::factory(),
            'title'      => sprintf('%d–%d', $year, $year + 1),
            'start_date' => "{$year}-09-01",
            'end_date'   => sprintf('%d-06-30', $year + 1),
            'status'     => AcademicYear::STATUS_ACTIVE,
        ];
    }
}
