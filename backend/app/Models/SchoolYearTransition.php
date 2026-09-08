<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolYearTransition extends Model
{
    use BelongsToSchool, HasFactory, HasUuid;

    protected $fillable = [
        'school_id', 'from_academic_year_id', 'to_academic_year_id',
        'status', 'summary', 'failure_reason', 'triggered_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['summary' => 'array', 'completed_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function fromAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'from_academic_year_id');
    }

    public function toAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'to_academic_year_id');
    }
}
