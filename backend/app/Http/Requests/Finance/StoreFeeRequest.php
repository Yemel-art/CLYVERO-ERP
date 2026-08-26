<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Models\SchoolClass;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFeeRequest extends FormRequest
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
            'academic_year_id' => ['required', 'uuid', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'class_id' => ['nullable', 'uuid', Rule::exists('school_classes', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(['tuition', 'cafeteria', 'uniform', 'transport', 'exam', 'other'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'frequency' => ['required', Rule::in(['one_time', 'monthly', 'termly', 'annual'])],
            'is_required' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('class_id') || $validator->errors()->hasAny(['class_id', 'academic_year_id'])) {
                return;
            }
            $class = SchoolClass::query()->find($this->input('class_id'));
            if (! $class || $class->academic_year_id !== $this->input('academic_year_id')) {
                $validator->errors()->add('class_id', 'The class must belong to the selected academic year.');
            }
        }];
    }
}
