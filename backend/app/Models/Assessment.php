<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToSchoolThroughRelation;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $term_id
 * @property string $class_id
 * @property string $subject_id
 * @property ?string $teacher_id
 * @property string $title
 * @property string $type
 * @property \Carbon\Carbon $date
 * @property float $max_score
 * @property float $weight
 * @property string $status
 */
class Assessment extends Model
{
    use Auditable, BelongsToSchoolThroughRelation, HasFactory, HasUuid;

    protected static function schoolTenantRelation(): string { return 'class.academicYear'; }

    public string $auditModule = 'grades';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'grade_sheet_id', 'term_id', 'class_id', 'subject_id', 'teacher_id',
        'title', 'type', 'date', 'max_score', 'weight',
        'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'max_score' => 'decimal:2', 'weight' => 'decimal:2'];
    }

    public function term(): BelongsTo        { return $this->belongsTo(Term::class); }
    public function class(): BelongsTo       { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function subject(): BelongsTo     { return $this->belongsTo(Subject::class); }
    public function teacher(): BelongsTo     { return $this->belongsTo(Teacher::class); }
    public function entries(): HasMany       { return $this->hasMany(GradeEntry::class); }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED);
    }
}
