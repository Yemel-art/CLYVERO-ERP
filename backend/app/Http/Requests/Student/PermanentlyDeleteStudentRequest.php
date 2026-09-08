<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PermanentlyDeleteStudentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirmation' => trim((string) $this->input('confirmation', '')),
        ]);
    }

    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student && ($this->user()?->can('delete', $student) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Student $student */
        $student = $this->route('student');

        return [
            'confirmation' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($student): void {
                    if (mb_strtoupper(trim((string) $value)) !== mb_strtoupper(trim($student->admission_number))) {
                        $fail('The matriculation number confirmation does not match this student.');
                    }
                },
            ],
            'reason' => ['required', Rule::in(['test_record', 'duplicate_record', 'registration_error'])],
            'acknowledge_permanent' => ['accepted'],
        ];
    }
}
