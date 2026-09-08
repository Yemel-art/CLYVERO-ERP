<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchoolThroughAcademicYear
{
    public static function bootBelongsToSchoolThroughAcademicYear(): void
    {
        static::addGlobalScope('school', function (Builder $query): void {
            if (app()->bound(TenantContext::class) && app(TenantContext::class)->hasSchool()) {
                $query->whereHas('academicYear', fn (Builder $year) => $year->where('school_id', app(TenantContext::class)->schoolId()));
            }
        });
    }
}
