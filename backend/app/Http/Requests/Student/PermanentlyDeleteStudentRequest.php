<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PermanentlyDeleteStudentRequest extends FormRequest
{
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
            'confirmation' => ['required', 'string', Rule::in([$student->admission_number])],
            'reason' => ['required', Rule::in(['test_record', 'duplicate_record', 'registration_error'])],
            'acknowledge_permanent' => ['accepted'],
        ];
    }
}
