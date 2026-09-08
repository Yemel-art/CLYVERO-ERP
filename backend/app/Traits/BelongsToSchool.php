<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $query): void {
            if (app()->bound(TenantContext::class) && app(TenantContext::class)->hasSchool()) {
                $query->where($query->qualifyColumn('school_id'), app(TenantContext::class)->schoolId());
            }
        });

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('school_id') && app()->bound(TenantContext::class)) {
                $model->setAttribute('school_id', app(TenantContext::class)->schoolId());
            }
        });
    }
}
