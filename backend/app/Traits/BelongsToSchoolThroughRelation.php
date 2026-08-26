<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/** Tenant isolation for models reached through a school-owned relation. */
trait BelongsToSchoolThroughRelation
{
    abstract protected static function schoolTenantRelation(): string;

    public static function bootBelongsToSchoolThroughRelation(): void
    {
        static::addGlobalScope('school', function (Builder $query): void {
            if (! app()->bound(TenantContext::class) || ! app(TenantContext::class)->hasSchool()) {
                return;
            }

            $query->whereHas(
                static::schoolTenantRelation(),
                fn (Builder $relation): Builder => $relation->where(
                    'school_id',
                    app(TenantContext::class)->schoolId(),
                ),
            );
        });
    }
}
