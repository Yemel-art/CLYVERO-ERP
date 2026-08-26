<?php

declare(strict_types=1);

namespace App\Http\Requests\Progression;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SavePromotionPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'grading_scale' => ['required', 'numeric', 'min:1', 'max:1000'],
            'passing_average' => ['required', 'numeric', 'min:0', 'lte:grading_scale'],
            'minimum_subject_mark' => ['required', 'numeric', 'min:0', 'lte:grading_scale'],
            'maximum_failed_subjects' => ['required', 'integer', 'min:0', 'max:100'],
            'critical_subject_ids' => ['nullable', 'array'],
            'critical_subject_ids.*' => [
                'uuid',
                Rule::exists('subjects', 'id')->where(
                    fn ($query) => $query->where('school_id', $this->user()?->school_id),
                ),
            ],
            'failure_conditions' => ['nullable', 'array'],
            'exclusion_conditions' => ['nullable', 'array'],
            'failure_conditions.match' => ['nullable', Rule::in(['all', 'any'])],
            'exclusion_conditions.match' => ['nullable', Rule::in(['all', 'any'])],
            'failure_conditions.average_below' => ['nullable', 'numeric', 'min:0', 'lte:grading_scale'],
            'failure_conditions.failed_subjects_at_least' => ['nullable', 'integer', 'min:0'],
            'failure_conditions.critical_failures_at_least' => ['nullable', 'integer', 'min:0'],
            'exclusion_conditions.average_below' => ['nullable', 'numeric', 'min:0', 'lte:grading_scale'],
            'exclusion_conditions.failed_subjects_at_least' => ['nullable', 'integer', 'min:0'],
            'exclusion_conditions.critical_failures_at_least' => ['nullable', 'integer', 'min:0'],
            'allow_class_council_override' => ['required', 'boolean'],
        ];
    }
}
