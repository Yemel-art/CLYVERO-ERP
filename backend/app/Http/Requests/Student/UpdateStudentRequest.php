<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\DTO\Student\StudentDTO;
use App\Enums\Gender;
use App\Enums\StudentCycle;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('student')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $student = $this->route('student');
        $studentId = $student?->id;
        $schoolId = app(TenantContext::class)->schoolId();
        $allowedPathways = array_values(array_unique(array_merge(
            array_keys(config('student.specialities', [])),
            array_keys(config('student.general_streams', [])),
        )));

        return [
            'admission_number' => ['sometimes', 'required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9\/._-]*$/',
                Rule::unique('students', 'admission_number')->where('school_id', $schoolId)->ignore($studentId)],
            'first_name' => ['sometimes', 'required', 'string', 'max:80'],
            'last_name' => ['sometimes', 'required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['sometimes', 'required', Rule::enum(Gender::class)],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before:today', 'after:1950-01-01'],
            'place_of_birth' => ['nullable', 'string', 'max:120'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'religion' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('students', 'email')->where('school_id', $schoolId)->ignore($studentId)->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'parent_id' => ['nullable', 'uuid', Rule::exists('parents', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'class_id' => ['sometimes', 'required', 'uuid', Rule::exists('school_classes', 'id')->where(fn ($query) => $query
                ->whereIn('academic_year_id', AcademicYear::query()->select('id')->where('school_id', $schoolId))
                ->whereNull('deleted_at'))],
            'academic_year_id' => ['sometimes', 'required', 'uuid', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'enrollment_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'previous_school' => ['nullable', 'string', 'max:160'],
            'cycle' => ['sometimes', 'required', Rule::in(array_keys(config('student.cycles')))],
            'speciality' => ['nullable', 'string', 'max:80', Rule::in($allowedPathways)],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'medical_conditions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDTO(): StudentDTO
    {
        $student = $this->route('student');

        return new StudentDTO(
            firstName: $this->input('first_name', $student->first_name),
            lastName: $this->input('last_name', $student->last_name),
            gender: Gender::from($this->input('gender', $student->gender->value)),
            dateOfBirth: CarbonImmutable::parse($this->input('date_of_birth', $student->date_of_birth->toDateString())),
            enrollmentDate: CarbonImmutable::parse($this->input('enrollment_date', $student->enrollment_date->toDateString())),
            admissionNumber: $this->input('admission_number', $student->admission_number),
            middleName: $this->input('middle_name', $student->middle_name),
            placeOfBirth: $this->input('place_of_birth', $student->place_of_birth),
            nationality: $this->input('nationality', $student->nationality),
            religion: $this->input('religion', $student->religion),
            email: $this->input('email', $student->email),
            phone: $this->input('phone', $student->phone),
            address: $this->input('address', $student->address),
            city: $this->input('city', $student->city),
            country: $this->input('country', $student->country),
            parentId: $this->input('parent_id', $student->parent_id),
            classId: $this->input('class_id', $student->class_id),
            academicYearId: $this->input('academic_year_id', $student->academic_year_id),
            previousSchool: $this->input('previous_school', $student->previous_school),
            cycle: $this->input('cycle', $student->cycle?->value ?? StudentCycle::SecondaryGeneral->value),
            speciality: $this->input('speciality', $student->speciality),
            emergencyContactName: $this->input('emergency_contact_name', $student->emergency_contact_name),
            emergencyContactPhone: $this->input('emergency_contact_phone', $student->emergency_contact_phone),
            emergencyContactRelationship: $this->input('emergency_contact_relationship', $student->emergency_contact_relationship),
            bloodGroup: $this->input('blood_group', $student->blood_group),
            allergies: $this->input('allergies', $student->allergies),
            medicalConditions: $this->input('medical_conditions', $student->medical_conditions),
        );
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['class_id', 'academic_year_id', 'cycle', 'speciality'])) return;
            $student = $this->route('student');
            $class = SchoolClass::query()->find($this->input('class_id', $student->class_id));
            if (! $class) return;
            $yearId = $this->input('academic_year_id', $student->academic_year_id);
            $cycle = $this->input('cycle', $student->cycle?->value ?? StudentCycle::SecondaryGeneral->value);
            $speciality = $this->input('speciality', $student->speciality);
            if ($class->academic_year_id !== $yearId) $validator->errors()->add('class_id', 'The selected class does not belong to the selected academic year.');
            if ($class->education_system !== $cycle) $validator->errors()->add('cycle', 'The education system must match the selected class.');
            if ($class->speciality !== $speciality) {
                $validator->errors()->add('speciality', 'The stream or speciality must match the selected class.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('admission_number')) {
            $this->merge(['admission_number' => mb_strtoupper(trim((string) $this->input('admission_number')))]);
        }
        $student = $this->route('student');
        $class = SchoolClass::query()->find($this->input('class_id', $student?->class_id));
        if ($class) {
            $this->merge([
                'cycle' => $class->education_system,
                'speciality' => $class->speciality,
            ]);
        }
    }
}
