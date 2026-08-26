<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class StoreTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:60'],
            'sequence'   => ['required', 'integer', 'between:1,5'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'status'     => ['sometimes', 'in:upcoming,active,closed'],
        ];
    }
}
