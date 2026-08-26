<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\DTO\Teacher\TeacherDTO;
use App\Enums\Gender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Teacher::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:80'],
            'last_name'       => ['required', 'string', 'max:80'],
            'middle_name'     => ['nullable', 'string', 'max:80'],
            'gender'          => ['required', Rule::enum(Gender::class)],
            'email'           => ['required', 'email', 'max:120', Rule::unique('teachers', 'email')->where('school_id', $this->user()?->school_id)->whereNull('deleted_at')],
            'phone'           => ['nullable', 'string', 'max:30'],
            'date_of_birth'   => ['nullable', 'date', 'before:today', 'after:1940-01-01'],
            'nationality'     => ['nullable', 'string', 'max:80'],
            'address'         => ['nullable', 'string', 'max:500'],
            'city'            => ['nullable', 'string', 'max:80'],
            'country'         => ['nullable', 'string', 'max:80'],
            'qualification'   => ['nullable', 'string', 'max:120'],
            'specialization'  => ['nullable', 'string', 'max:120'],
            'position'        => ['nullable', 'string', 'max:120'],
            'department'      => ['nullable', 'string', 'max:120'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'hire_date'       => ['required', 'date', 'before_or_equal:today'],
            'salary'          => ['nullable', 'numeric', 'min:0'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'create_user_account'     => ['sometimes', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function toDTO(): TeacherDTO
    {
        return new TeacherDTO(
            firstName:         $this->string('first_name')->toString(),
            lastName:          $this->string('last_name')->toString(),
            gender:            Gender::from($this->string('gender')->toString()),
            email:             strtolower($this->string('email')->toString()),
            hireDate:          CarbonImmutable::parse($this->string('hire_date')->toString()),
            middleName:        $this->input('middle_name'),
            dateOfBirth:       $this->input('date_of_birth') ? CarbonImmutable::parse((string) $this->input('date_of_birth')) : null,
            nationality:       $this->input('nationality'),
            phone:             $this->input('phone'),
            address:           $this->input('address'),
            city:              $this->input('city'),
            country:           $this->input('country'),
            qualification:     $this->input('qualification'),
            specialization:    $this->input('specialization'),
            position:          $this->input('position'),
            department:        $this->input('department'),
            yearsOfExperience: $this->input('years_of_experience') !== null ? (int) $this->input('years_of_experience') : null,
            salary:            $this->input('salary') !== null ? (float) $this->input('salary') : null,
            emergencyContactName:  $this->input('emergency_contact_name'),
            emergencyContactPhone: $this->input('emergency_contact_phone'),
            createUserAccount: $this->boolean('create_user_account', true),
        );
    }
}
