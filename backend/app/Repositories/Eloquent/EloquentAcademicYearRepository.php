<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\AcademicYear;
use App\Repositories\Contracts\AcademicYearRepositoryInterface;

class EloquentAcademicYearRepository extends BaseRepository implements AcademicYearRepositoryInterface
{
    protected function model(): string { return AcademicYear::class; }
}
