<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Adds UUID v7-style primary keys to any model.
 *
 * - Generates a UUID on `creating` if `id` is not already set.
 * - Forces the key type to string and disables auto-incrementing.
 *
 * Source: Database Specification — "UUID Primary Keys" mandate.
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public function getIncrementing(): bool
    {
        return false;
    }
}
