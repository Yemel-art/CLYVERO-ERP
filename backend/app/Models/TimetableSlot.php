<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToSchoolThroughRelation;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $class_id
 * @property ?string $subject_id
 * @property ?string $teacher_id
 * @property string $day_of_week
 * @property string $start_time
 * @property string $end_time
 */
class TimetableSlot extends Model
{
    use Auditable, BelongsToSchoolThroughRelation, HasFactory, HasUuid;

    protected static function schoolTenantRelation(): string { return 'class.academicYear'; }

    public string $auditModule = 'timetable';

    protected $fillable = [
        'class_id', 'subject_id', 'teacher_id',
        'day_of_week', 'start_time', 'end_time',
        'room', 'notes',
    ];

    public function class(): BelongsTo   { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(Teacher::class); }
}
