<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('report_card.generate') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'language' => ['sometimes', 'in:fr,en'],
            'academic_year_id' => ['sometimes', 'uuid', 'exists:academic_years,id'],
        ];
    }
}
