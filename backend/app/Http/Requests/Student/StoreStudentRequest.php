<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\DTO\Student\StudentDTO;
use App\Enums\Gender;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Student::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->schoolId();
        $allowedPathways = array_values(array_unique(array_merge(
            array_keys(config('student.specialities', [])),
            array_keys(config('student.general_streams', [])),
        )));
        return [
            // Identity
            'admission_number' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9][A-Z0-9\/._-]*$/',
                Rule::unique('students', 'admission_number')
                    ->where('school_id', $schoolId),
            ],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1950-01-01'],
            'place_of_birth' => ['nullable', 'string', 'max:120'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'religion' => ['nullable', 'string', 'max:80'],

            // Contact
            'email' => ['nullable', 'email', 'max:120', Rule::unique('students', 'email')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],

            // Enrollment
            'parent_id' => ['nullable', 'uuid'],
            'guardian_mode' => ['required', Rule::in(['existing', 'new'])],
            'existing_parent_id' => [
                'nullable',
                'uuid',
                Rule::requiredIf(fn () => $this->input('guardian_mode') === 'existing'),
                Rule::exists('parents', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'parent_first_name' => ['nullable', 'string', 'max:80', Rule::requiredIf(fn () => $this->input('guardian_mode') === 'new')],
            'parent_last_name' => ['nullable', 'string', 'max:80', Rule::requiredIf(fn () => $this->input('guardian_mode') === 'new')],
            'parent_gender' => ['nullable', Rule::enum(Gender::class), Rule::requiredIf(fn () => $this->input('guardian_mode') === 'new')],
            'parent_email' => [
                'nullable',
                'email',
                'max:120',
                Rule::requiredIf(fn () => $this->input('guardian_mode') === 'new'),
                Rule::unique('parents', 'email')->where('school_id', $schoolId)->whereNull('deleted_at'),
                Rule::unique('users', 'email')->where('school_id', $schoolId),
            ],
            'parent_phone' => ['nullable', 'string', 'max:30', Rule::requiredIf(fn () => $this->input('guardian_mode') === 'new')],
            'parent_relationship' => ['required', 'string', 'max:60'],
            'create_parent_account' => ['sometimes', 'boolean'],
            'class_id' => ['required', 'uuid', Rule::exists('school_classes', 'id')->where(fn ($query) => $query->whereIn('academic_year_id', AcademicYear::query()->select('id')->where('school_id', $schoolId)))],
            'academic_year_id' => ['required', 'uuid', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'enrollment_date' => ['required', 'date', 'before_or_equal:today'],
            'previous_school' => ['nullable', 'string', 'max:160'],
            'initial_payment' => ['nullable', 'numeric', 'min:0'],

            // ─── Cycle & speciality ─────────────────────────────────
            'cycle' => [
                'required',
                Rule::in(array_keys(config('student.cycles'))),
            ],
            'speciality' => [
                'nullable',
                'string',
                'max:80',
                Rule::in($allowedPathways),
            ],

            // Emergency contact
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],

            // Health (optional, sensitive)
            'blood_group' => ['nullable', 'string', 'max:5'],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'medical_conditions' => ['nullable', 'string', 'max:1000'],

            // Photo (handled separately, but accepted on the same request)
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'], // 5 MB
        ];
    }

    public function toDTO(): StudentDTO
    {
        return new StudentDTO(
            firstName: $this->string('first_name')->toString(),
            lastName: $this->string('last_name')->toString(),
            gender: Gender::from($this->string('gender')->toString()),
            dateOfBirth: CarbonImmutable::parse($this->string('date_of_birth')->toString()),
            enrollmentDate: CarbonImmutable::parse($this->string('enrollment_date')->toString()),
            admissionNumber: $this->string('admission_number')->toString(),
            middleName: $this->input('middle_name'),
            placeOfBirth: $this->input('place_of_birth'),
            nationality: $this->input('nationality'),
            religion: $this->input('religion'),
            email: $this->input('email'),
            phone: $this->input('phone'),
            address: $this->input('address'),
            city: $this->input('city'),
            country: $this->input('country'),
            parentId: $this->input('parent_id'),
            classId: $this->input('class_id'),
            academicYearId: $this->input('academic_year_id'),
            previousSchool: $this->input('previous_school'),
            cycle: $this->input('cycle'),
            speciality: $this->input('speciality'),
            emergencyContactName: $this->input('emergency_contact_name'),
            emergencyContactPhone: $this->input('emergency_contact_phone'),
            emergencyContactRelationship: $this->input('emergency_contact_relationship'),
            bloodGroup: $this->input('blood_group'),
            allergies: $this->input('allergies'),
            medicalConditions: $this->input('medical_conditions'),
        );
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['class_id', 'academic_year_id', 'cycle', 'speciality'])) return;
            $class = SchoolClass::query()->find($this->input('class_id'));
            if (! $class) return;
            if ($class->academic_year_id !== $this->input('academic_year_id')) {
                $validator->errors()->add('class_id', 'The selected class does not belong to the selected academic year.');
            }
            if ($class->education_system !== $this->input('cycle')) {
                $validator->errors()->add('cycle', 'The education system must match the selected class.');
            }
            if ($class->speciality !== $this->input('speciality')) {
                $validator->errors()->add('speciality', 'The stream or speciality must match the selected class.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('admission_number')) {
            $this->merge([
                'admission_number' => mb_strtoupper(trim((string) $this->input('admission_number'))),
            ]);
        }
        $class = SchoolClass::query()->find($this->input('class_id'));
        if ($class) {
            // Academic placement is authoritative. Never trust a separate
            // browser field that can disagree with the selected class.
            $this->merge([
                'cycle' => $class->education_system,
                'speciality' => $class->speciality,
            ]);
        }
    }
}
