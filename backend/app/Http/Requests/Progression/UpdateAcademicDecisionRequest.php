<?php

declare(strict_types=1);

namespace App\Http\Requests\Progression;

use App\Enums\AcademicDecisionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAcademicDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('grade.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'final_decision' => ['sometimes', Rule::enum(AcademicDecisionStatus::class)],
            'override_reason' => ['required_with:final_decision', 'nullable', 'string', 'max:1000'],
            'teacher_appreciation' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'class_council_recommendation' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
