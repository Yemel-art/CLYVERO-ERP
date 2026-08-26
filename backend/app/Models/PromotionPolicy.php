<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionPolicy extends Model
{
    use BelongsToSchool, HasFactory, HasUuid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'name', 'grading_scale',
        'passing_average', 'minimum_subject_mark', 'maximum_failed_subjects',
        'critical_subject_ids', 'failure_conditions', 'exclusion_conditions',
        'allow_class_council_override', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'grading_scale' => 'decimal:2',
            'passing_average' => 'decimal:2',
            'minimum_subject_mark' => 'decimal:2',
            'maximum_failed_subjects' => 'integer',
            'critical_subject_ids' => 'array',
            'failure_conditions' => 'array',
            'exclusion_conditions' => 'array',
            'allow_class_council_override' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
