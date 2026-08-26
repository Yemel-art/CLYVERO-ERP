<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:1', 'max:128'],
            'school_slug' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9-]+$/'],
            'remember_me' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        if ($this->filled('school_slug')) $this->merge(['school_slug' => trim((string) $this->input('school_slug'))]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
            'school_slug.regex' => 'The school code may contain letters, numbers, and hyphens only.',
        ];
    }
}
