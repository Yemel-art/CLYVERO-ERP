<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payment.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'paid_at'   => ['required', 'date'],
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'method'    => ['required', 'in:cash,bank_transfer,mtn_momo,orange_money,cheque,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes'     => ['nullable', 'string', 'max:300'],
        ];
    }
}
