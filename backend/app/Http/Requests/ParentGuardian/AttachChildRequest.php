<?php

declare(strict_types=1);

namespace App\Http\Requests\ParentGuardian;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('parent')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_id'   => ['required', 'uuid', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'relationship' => ['required', 'string', 'max:60'],
            'is_primary'   => ['sometimes', 'boolean'],
            'can_pickup'   => ['sometimes', 'boolean'],
        ];
    }
}
