<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToSchoolThroughRelation;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStructure extends Model
{
    use Auditable, BelongsToSchoolThroughRelation, HasFactory, HasUuid;

    protected static function schoolTenantRelation(): string { return 'academicYear'; }

    public string $auditModule = 'finance';

    protected $fillable = [
        'academic_year_id', 'class_id', 'name', 'category',
        'amount', 'frequency', 'is_required', 'description',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_required' => 'boolean'];
    }

    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function schoolClass(): BelongsTo  { return $this->belongsTo(SchoolClass::class, 'class_id'); }
}
