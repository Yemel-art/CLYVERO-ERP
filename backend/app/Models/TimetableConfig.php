<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToSchoolThroughRelation;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableConfig extends Model
{
    use BelongsToSchoolThroughRelation, HasUuid;

    protected static function schoolTenantRelation(): string { return 'academicYear'; }

    protected $fillable = ['academic_year_id', 'working_days', 'periods', 'break_periods'];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'periods' => 'array',
            'break_periods' => 'array',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
