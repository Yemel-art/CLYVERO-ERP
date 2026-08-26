<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToSchoolThroughRelation;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $class_id
 * @property ?string $term_id
 * @property ?string $taken_by
 * @property \Carbon\Carbon $date
 * @property ?string $period
 * @property string $status
 */
class AttendanceSession extends Model
{
    use Auditable, BelongsToSchoolThroughRelation, HasFactory, HasUuid;

    protected static function schoolTenantRelation(): string { return 'class.academicYear'; }

    public string $auditModule = 'attendance';

    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = ['class_id', 'term_id', 'taken_by', 'date', 'period', 'status', 'notes'];

    protected function casts(): array { return ['date' => 'date']; }

    public function class(): BelongsTo            { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function term(): BelongsTo             { return $this->belongsTo(Term::class); }
    public function takenBy(): BelongsTo          { return $this->belongsTo(User::class, 'taken_by'); }
    public function records(): HasMany            { return $this->hasMany(AttendanceRecord::class, 'session_id'); }
}
