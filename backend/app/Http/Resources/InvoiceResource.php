<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'invoice_number'   => $this->invoice_number,
            'student_id'       => $this->student_id,
            'academic_year_id' => $this->academic_year_id,
            'issued_at'        => $this->issued_at->toDateString(),
            'due_at'           => $this->due_at->toDateString(),
            'subtotal'         => (float) $this->subtotal,
            'discount'         => (float) $this->discount,
            'total'            => (float) $this->total,
            'paid'             => (float) $this->paid,
            'balance'          => (float) $this->balance,
            'status'           => $this->status,
            'notes'            => $this->notes,
            'student'          => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id, 'full_name' => $this->student->full_name,
                'admission_number' => $this->student->admission_number, 'photo_url' => $this->student->photo_url,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id, 'description' => $i->description, 'quantity' => $i->quantity,
                'unit_amount' => (float) $i->unit_amount, 'line_total' => (float) $i->line_total,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id, 'receipt_number' => $p->receipt_number,
                'paid_at' => $p->paid_at->toDateString(), 'amount' => (float) $p->amount,
                'method' => $p->method, 'reference' => $p->reference,
            ])->all()),
        ];
    }
}
