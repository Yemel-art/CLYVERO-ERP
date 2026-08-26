<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToSchoolThroughRelation;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use BelongsToSchoolThroughRelation, HasFactory, HasUuid;

    protected static function schoolTenantRelation(): string { return 'invoice.student'; }

    protected $fillable = ['invoice_id', 'fee_structure_id', 'description', 'quantity', 'unit_amount', 'line_total'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer', 'unit_amount' => 'decimal:2', 'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo      { return $this->belongsTo(Invoice::class); }
    public function feeStructure(): BelongsTo { return $this->belongsTo(FeeStructure::class); }
}
