<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToSchoolThroughRelation;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use Auditable, BelongsToSchoolThroughRelation, HasFactory, HasUuid;

    protected static function schoolTenantRelation(): string { return 'student'; }

    public string $auditModule = 'finance';

    protected $fillable = [
        'receipt_number', 'invoice_id', 'student_id',
        'paid_at', 'amount', 'method', 'reference', 'notes', 'received_by',
        'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'amount' => 'decimal:2', 'voided_at' => 'datetime'];
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function voidedBy(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
}
