<?php

declare(strict_types=1);

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('timetable.edit') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'replace_existing' => ['sometimes', 'boolean'],
        ];
    }
}
