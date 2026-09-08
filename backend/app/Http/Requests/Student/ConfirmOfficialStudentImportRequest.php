<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConfirmOfficialStudentImportRequest extends FormRequest
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
            'class_mapping' => ['required', 'array', 'min:1', 'max:200'],
            'class_mapping.*.source_class' => ['required', 'string', 'max:160', 'distinct'],
            'class_mapping.*.class_id' => [
                'required', 'uuid',
                Rule::exists('school_classes', 'id')->where(fn ($query) => $query
                    ->whereIn('academic_year_id', fn ($years) => $years->select('id')->from('academic_years')->where('school_id', $schoolId))
                    ->whereNull('deleted_at')),
            ],
            'duplicate_action' => ['sometimes', Rule::in(['skip', 'update'])],
        ];
    }
}
