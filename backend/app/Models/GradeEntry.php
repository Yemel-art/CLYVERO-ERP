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
 * @property string $assessment_id
 * @property string $student_id
 * @property ?float $score
 * @property ?string $grade_letter
 * @property ?string $comment
 * @property ?string $graded_by
 */
class GradeEntry extends Model
{
    use Auditable, BelongsToSchoolThroughRelation, HasFactory, HasUuid;

    protected static function schoolTenantRelation(): string { return 'student'; }

    public string $auditModule = 'grades';

    protected $fillable = [
        'assessment_id', 'student_id', 'score',
        'grade_letter', 'comment', 'graded_by', 'graded_at',
    ];

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'graded_at' => 'datetime'];
    }

    public function assessment(): BelongsTo { return $this->belongsTo(Assessment::class); }
    public function student(): BelongsTo    { return $this->belongsTo(Student::class); }
    public function gradedBy(): BelongsTo   { return $this->belongsTo(User::class, 'graded_by'); }
}
