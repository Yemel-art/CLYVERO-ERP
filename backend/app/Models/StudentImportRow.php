<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StudentImportRow extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'student_import_id', 'row_number', 'fingerprint', 'raw_data',
        'normalized_data', 'status', 'action', 'errors', 'warnings', 'student_id',
        'invoice_id', 'fee_assigned',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array', 'normalized_data' => 'array', 'errors' => 'array',
            'warnings' => 'array', 'fee_assigned' => 'boolean',
        ];
    }

    public function studentImport(): BelongsTo { return $this->belongsTo(StudentImport::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
