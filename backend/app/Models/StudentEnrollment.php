<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentEnrollment extends Model
{
    use BelongsToSchool, HasFactory, HasUuid;

    protected $fillable = [
        'school_id', 'student_id', 'academic_year_id', 'class_id',
        'enrolled_at', 'status', 'source_enrollment_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['enrolled_at' => 'date', 'status' => EnrollmentStatus::class];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function sourceEnrollment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_enrollment_id');
    }

    public function academicDecision(): HasOne
    {
        return $this->hasOne(AcademicDecision::class, 'enrollment_id');
    }
}
