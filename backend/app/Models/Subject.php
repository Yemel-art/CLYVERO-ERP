<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string $code
 * @property float $coefficient
 * @property string $color
 * @property bool $is_active
 */
class Subject extends Model
{
    use Auditable, BelongsToSchool, HasFactory, HasUuid, SoftDeletes;

    public string $auditModule = 'subject';

    public const COLOR_PALETTE = [
        '#2563eb', '#7c3aed', '#dc2626', '#16a34a', '#ea580c',
        '#0891b2', '#db2777', '#ca8a04', '#4f46e5', '#9333ea',
        '#0f766e', '#65a30d', '#f59e0b', '#be123c', '#475569',
    ];

    protected $fillable = ['school_id', 'name', 'code', 'education_system', 'coefficient', 'color', 'description', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'coefficient' => 'decimal:2'];
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject', 'subject_id', 'class_id')
            ->withPivot(['teacher_id', 'coefficient', 'weekly_frequency'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return $q->where(fn (Builder $q) => $q->where('name', 'ilike', $like)->orWhere('code', 'ilike', $like));
    }
}
