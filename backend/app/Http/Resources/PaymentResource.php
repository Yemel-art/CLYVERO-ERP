<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'receipt_number' => $this->receipt_number,
            'invoice_id'     => $this->invoice_id,
            'student_id'     => $this->student_id,
            'paid_at'        => $this->paid_at->toDateString(),
            'amount'         => (float) $this->amount,
            'method'         => $this->method,
            'reference'      => $this->reference,
            'notes'          => $this->notes,
            'voided_at'      => $this->voided_at?->toIso8601String(),
            'void_reason'    => $this->void_reason,
        ];
    }
}
