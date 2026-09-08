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

class Invoice extends Model
{
    use Auditable, BelongsToSchoolThroughRelation, HasFactory, HasUuid;

    protected static function schoolTenantRelation(): string { return 'student'; }

    public string $auditModule = 'finance';

    public const STATUS_DRAFT          = 'draft';
    public const STATUS_ISSUED         = 'issued';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID           = 'paid';
    public const STATUS_CANCELLED      = 'cancelled';
    public const STATUS_OVERDUE        = 'overdue';

    protected $fillable = [
        'invoice_number', 'student_id', 'academic_year_id',
        'issued_at', 'due_at', 'subtotal', 'discount', 'total',
        'paid', 'balance', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date', 'due_at' => 'date',
            'subtotal' => 'decimal:2', 'discount' => 'decimal:2',
            'total'    => 'decimal:2', 'paid'    => 'decimal:2', 'balance' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo      { return $this->belongsTo(Student::class); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function items(): HasMany          { return $this->hasMany(InvoiceItem::class); }
    public function payments(): HasMany       { return $this->hasMany(Payment::class); }
}
