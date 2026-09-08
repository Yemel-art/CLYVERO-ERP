<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payment.view') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'copies' => ['sometimes', 'integer', 'min:1', 'max:3'],
            'language' => ['sometimes', 'in:fr,en'],
        ];
    }
}
