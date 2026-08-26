<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\DTO\Teacher\TeacherDTO;
use App\Enums\Gender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('teacher')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $teacherId = $this->route('teacher')?->id;
        return [
            'first_name'      => ['sometimes', 'required', 'string', 'max:80'],
            'last_name'       => ['sometimes', 'required', 'string', 'max:80'],
            'middle_name'     => ['nullable', 'string', 'max:80'],
            'gender'          => ['sometimes', 'required', Rule::enum(Gender::class)],
            'email' => [
                'sometimes', 'required', 'email', 'max:120',
                Rule::unique('teachers', 'email')->where('school_id', $this->user()?->school_id)->ignore($teacherId)->whereNull('deleted_at'),
            ],
            'phone'           => ['nullable', 'string', 'max:30'],
            'date_of_birth'   => ['nullable', 'date', 'before:today', 'after:1940-01-01'],
            'nationality'     => ['nullable', 'string', 'max:80'],
            'address'         => ['nullable', 'string', 'max:500'],
            'city'            => ['nullable', 'string', 'max:80'],
            'country'         => ['nullable', 'string', 'max:80'],
            'qualification'   => ['nullable', 'string', 'max:120'],
            'specialization'  => ['nullable', 'string', 'max:120'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'hire_date'       => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'salary'          => ['nullable', 'numeric', 'min:0'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function toDTO(): TeacherDTO
    {
        $t = $this->route('teacher');
        return new TeacherDTO(
            firstName:  $this->input('first_name', $t->first_name),
            lastName:   $this->input('last_name', $t->last_name),
            gender:     Gender::from($this->input('gender', $t->gender->value)),
            email:      strtolower($this->input('email', $t->email)),
            hireDate:   CarbonImmutable::parse($this->input('hire_date', $t->hire_date->toDateString())),
            middleName: $this->input('middle_name', $t->middle_name),
            dateOfBirth: $this->input('date_of_birth')
                ? CarbonImmutable::parse((string) $this->input('date_of_birth'))
                : ($t->date_of_birth ? CarbonImmutable::parse($t->date_of_birth->toDateString()) : null),
            nationality:    $this->input('nationality', $t->nationality),
            phone:          $this->input('phone', $t->phone),
            address:        $this->input('address', $t->address),
            city:           $this->input('city', $t->city),
            country:        $this->input('country', $t->country),
            qualification:  $this->input('qualification', $t->qualification),
            specialization: $this->input('specialization', $t->specialization),
            yearsOfExperience: $this->input('years_of_experience') !== null ? (int) $this->input('years_of_experience') : $t->years_of_experience,
            salary:         $this->input('salary') !== null ? (float) $this->input('salary') : ($t->salary !== null ? (float) $t->salary : null),
            emergencyContactName:  $this->input('emergency_contact_name', $t->emergency_contact_name),
            emergencyContactPhone: $this->input('emergency_contact_phone', $t->emergency_contact_phone),
            createUserAccount: false,
        );
    }
}
