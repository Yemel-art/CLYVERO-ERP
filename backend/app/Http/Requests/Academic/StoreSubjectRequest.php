<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() || $this->user()?->isSecretary();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('subject')?->id;
        $schoolId = app(TenantContext::class)->schoolId();
        return [
            'name'        => ['required', 'string', 'max:120', Rule::unique('subjects', 'name')->where('school_id', $schoolId)->ignore($id)->whereNull('deleted_at')],
            'code'        => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->where('school_id', $schoolId)->ignore($id)->whereNull('deleted_at')],
            'education_system' => ['required', Rule::in(['both', 'secondary_general', 'secondary_technical'])],
            'coefficient' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'color'       => [
                'nullable',
                'string',
                'regex:/^#[0-9a-fA-F]{6}$/',
                Rule::unique('subjects', 'color')->where('school_id', $schoolId)->ignore($id)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
