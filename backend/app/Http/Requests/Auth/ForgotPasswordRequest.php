<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'school_slug' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        if ($this->has('school_slug')) $this->merge(['school_slug' => trim((string) $this->input('school_slug'))]);
    }
}
