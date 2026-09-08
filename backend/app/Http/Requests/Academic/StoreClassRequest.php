<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Models\SchoolClass;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreClassRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $language = (string) $this->input('language', 'en');
        $system = (string) $this->input('education_system', 'secondary_general');
        $speciality = trim((string) $this->input('speciality', ''));
        $cycle = trim((string) $this->input('cycle', ''));
        $level = trim((string) $this->input('grade_level', ''));
        $systemLabel = SchoolClass::EDUCATION_SYSTEMS[$system] ?? $system;
        $cycleLabel = SchoolClass::CYCLES[$cycle] ?? $cycle;
        $specialityLabel = '';
        if ($speciality !== '') {
            $catalog = $system === 'secondary_technical' ? 'specialities' : 'general_streams';
            $config = config("student.{$catalog}.{$speciality}", []);
            $specialityLabel = $config === []
                ? $speciality
                : sprintf('%s (%s)', strtoupper((string) ($config['name_en'] ?? $speciality)), (string) ($config['name'] ?? $speciality));
        }

        $this->merge([
            'speciality' => $speciality !== '' ? $speciality : null,
            'name' => implode(' · ', array_filter([
                $systemLabel,
                $specialityLabel,
                $cycleLabel,
                $level,
                $language === 'fr' ? 'Programme français' : 'English programme',
            ])),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() || $this->user()?->isSecretary();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('class')?->id;
        $schoolId = app(TenantContext::class)->schoolId();
        $system = (string) $this->input('education_system', 'secondary_general');
        $language = (string) $this->input('language', 'en');
        $cycle = (string) $this->input('cycle', '');
        $allowedStreams = $system === 'secondary_technical'
            ? array_keys(config('student.specialities', []))
            : array_keys(config('student.general_streams', []));
        $requiresStream = $system === 'secondary_technical'
            || ($system === 'secondary_general' && $cycle === 'second_cycle');

        return [
            'academic_year_id' => ['required', 'uuid', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'education_system' => ['required', Rule::in(array_keys(SchoolClass::EDUCATION_SYSTEMS))],
            'form_master_id' => ['nullable', 'uuid', Rule::exists('teachers', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255', Rule::unique('school_classes', 'name')->where('academic_year_id', $this->input('academic_year_id'))->ignore($id)->whereNull('deleted_at')],
            'speciality' => [
                'nullable',
                Rule::requiredIf(fn () => $requiresStream),
                Rule::in($allowedStreams),
            ],
            'cycle' => ['required', Rule::in(array_keys(SchoolClass::CYCLES))],
            'grade_level' => ['required', Rule::in(SchoolClass::levelsForCycle($this->input('cycle'), $system, $language))],
            'language' => ['required', Rule::in(['fr', 'en'])],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
