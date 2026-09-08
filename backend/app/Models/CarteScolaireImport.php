<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CarteScolaireImport extends Model
{
    use BelongsToSchool;
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'created_by', 'approved_by',
        'source_filename', 'source_hash', 'status', 'preview_rows', 'summary',
        'approved_at', 'imported_at', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'preview_rows' => 'array',
            'summary' => 'array',
            'approved_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
