<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StudentImport extends Model
{
    use BelongsToSchool, HasFactory, HasUuid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'source', 'original_filename', 'status',
        'detected_columns', 'column_mapping', 'class_mapping', 'duplicate_action', 'summary', 'error_report',
        'imported_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'detected_columns' => 'array', 'column_mapping' => 'array', 'class_mapping' => 'array',
            'summary' => 'array', 'error_report' => 'array', 'completed_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function rows(): HasMany { return $this->hasMany(StudentImportRow::class); }
    public function importer(): BelongsTo { return $this->belongsTo(User::class, 'imported_by'); }
}
