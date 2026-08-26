<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdministrator() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'school_code' => $this->filled('school_code') ? strtoupper(trim((string) $this->input('school_code'))) : null,
            'email' => strtolower(trim((string) $this->input('email'))),
            'administrator_email' => strtolower(trim((string) $this->input('administrator_email'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:160'],
            'school_code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', Rule::unique('schools', 'school_code')],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('schools', 'slug')],
            'email' => ['required', 'email', 'max:160', Rule::unique('schools', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'default_locale' => ['required', Rule::in(['fr', 'en'])],
            'education_systems' => ['required', 'array', 'min:1'],
            'education_systems.*' => [Rule::in(['secondary_general', 'secondary_technical'])],
            'administrator_first_name' => ['required', 'string', 'max:80'],
            'administrator_last_name' => ['required', 'string', 'max:80'],
            'administrator_email' => ['required', 'email', 'max:160'],
            'administrator_password' => ['required', 'string', new StrongPassword()],
        ];
    }
}
