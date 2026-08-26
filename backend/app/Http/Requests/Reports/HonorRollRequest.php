<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class HonorRollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('report_card.generate') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'term_id' => ['required', 'uuid', 'exists:terms,id'],
            'class_id' => ['sometimes', 'nullable', 'uuid', 'exists:school_classes,id'],
            'language' => ['sometimes', 'in:fr,en'],
        ];
    }
}
