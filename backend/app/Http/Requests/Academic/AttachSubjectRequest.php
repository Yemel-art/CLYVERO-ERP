<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() || $this->user()?->isSecretary();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->schoolId();

        return [
            'subject_id' => ['required', 'uuid', Rule::exists('subjects', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'teacher_id' => ['nullable', 'uuid', Rule::exists('teachers', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'coefficient' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'weekly_frequency' => ['nullable', 'integer', 'min:1', 'max:15'],
        ];
    }
}
