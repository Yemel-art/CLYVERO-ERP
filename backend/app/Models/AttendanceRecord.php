<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Traits\Auditable;
use App\Traits\BelongsToSchoolThroughRelation;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $session_id
 * @property string $student_id
 * @property AttendanceStatus $status
 */
class AttendanceRecord extends Model
{
    use Auditable, BelongsToSchoolThroughRelation, HasFactory, HasUuid;

    protected static function schoolTenantRelation(): string { return 'student'; }

    public string $auditModule = 'attendance';

    protected $fillable = ['session_id', 'student_id', 'status', 'notes'];

    protected function casts(): array
    {
        return ['status' => AttendanceStatus::class];
    }

    public function session(): BelongsTo { return $this->belongsTo(AttendanceSession::class, 'session_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
