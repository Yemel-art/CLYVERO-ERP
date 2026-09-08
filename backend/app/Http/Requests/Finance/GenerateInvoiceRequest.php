<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('invoice.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->schoolId();

        return [
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'academic_year_id' => ['required', 'uuid', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'due_at'           => ['nullable', 'date'],
        ];
    }
}
