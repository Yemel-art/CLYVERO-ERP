<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use App\Traits\BelongsToSchoolThroughAcademicYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $academic_year_id
 * @property string $name
 * @property int $sequence
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon $end_date
 * @property string $status
 */
class Term extends Model
{
    use Auditable, BelongsToSchoolThroughAcademicYear, HasFactory, HasUuid;

    public string $auditModule = 'term';

    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_CLOSED   = 'closed';

    protected $fillable = [
        'academic_year_id', 'name', 'sequence',
        'start_date', 'end_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'sequence'   => 'integer',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }
}
