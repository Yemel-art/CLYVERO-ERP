<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

final class AnalyzeStudentImportRequest extends FormRequest
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
        return ['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240']];
    }
}
