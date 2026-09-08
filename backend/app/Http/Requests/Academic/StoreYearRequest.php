<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->schoolId();

        return [
            'title' => [
                'required', 'string', 'regex:/^\d{4}\/\d{4}$/',
                Rule::unique('academic_years', 'title')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('title')) {
            $title = preg_replace('/\s*[\-\x{2013}\x{2014}]\s*/u', '/', trim((string) $this->input('title')));
            $this->merge(['title' => $title]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! preg_match('/^(\d{4})\/(\d{4})$/', (string) $this->input('title'), $matches)) {
                return;
            }
            if ((int) $matches[2] !== (int) $matches[1] + 1) {
                $validator->errors()->add('title', 'The academic year must contain two consecutive years.');
            }
            if ($this->input('start_date') !== $matches[1].'-09-01') {
                $validator->errors()->add('start_date', 'The academic year must start on 1 September of its first year.');
            }
            if ($this->input('end_date') !== $matches[2].'-06-30') {
                $validator->errors()->add('end_date', 'The academic year must end on 30 June of its second year.');
            }
        }];
    }
}
