<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AcademicDecisionStatus;
use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicDecision extends Model
{
    use BelongsToSchool, HasFactory, HasUuid;

    protected $fillable = [
        'school_id', 'student_id', 'enrollment_id', 'academic_year_id', 'promotion_policy_id',
        'computed_decision', 'final_decision', 'final_average', 'failed_subjects_count',
        'failed_subject_ids', 'decision_reasons', 'teacher_appreciation',
        'class_council_recommendation', 'override_reason', 'overridden_by',
        'overridden_at', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'computed_decision' => AcademicDecisionStatus::class,
            'final_decision' => AcademicDecisionStatus::class,
            'final_average' => 'decimal:2',
            'failed_subjects_count' => 'integer',
            'failed_subject_ids' => 'array',
            'decision_reasons' => 'array',
            'overridden_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function promotionPolicy(): BelongsTo
    {
        return $this->belongsTo(PromotionPolicy::class);
    }
}
