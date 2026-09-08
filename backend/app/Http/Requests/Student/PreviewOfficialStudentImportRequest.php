<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewOfficialStudentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return ($user?->hasPermission('student.create') ?? false)
            && ($user->isAdministrator() || $user->isSecretary());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->schoolId();

        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'academic_year_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)->where('status', 'active')->whereNull('deleted_at')],
            'column_mapping' => ['sometimes', 'array', 'max:20'],
            'column_mapping.*' => ['nullable', 'string', 'max:255', 'distinct'],
        ];
    }
}
