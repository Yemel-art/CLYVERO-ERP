<?php

declare(strict_types=1);

namespace App\DTO\Teacher;

use App\Enums\Gender;
use Carbon\CarbonImmutable;

final readonly class TeacherDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public Gender $gender,
        public string $email,
        public CarbonImmutable $hireDate,

        public ?string $middleName        = null,
        public ?CarbonImmutable $dateOfBirth = null,
        public ?string $nationality       = null,
        public ?string $phone             = null,
        public ?string $address           = null,
        public ?string $city              = null,
        public ?string $country           = null,
        public ?string $qualification     = null,
        public ?string $specialization    = null,
        public ?string $position          = null,
        public ?string $department        = null,
        public ?int    $yearsOfExperience = null,
        public ?float  $salary            = null,
        public ?string $emergencyContactName  = null,
        public ?string $emergencyContactPhone = null,
        public bool    $createUserAccount = true,
    ) {
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        $map = [
            'first_name'              => $this->firstName,
            'last_name'               => $this->lastName,
            'gender'                  => $this->gender->value,
            'email'                   => $this->email,
            'hire_date'               => $this->hireDate->toDateString(),
            'middle_name'             => $this->middleName,
            'date_of_birth'           => $this->dateOfBirth?->toDateString(),
            'nationality'             => $this->nationality,
            'phone'                   => $this->phone,
            'address'                 => $this->address,
            'city'                    => $this->city,
            'country'                 => $this->country,
            'qualification'           => $this->qualification,
            'specialization'          => $this->specialization,
            'position'                => $this->position,
            'department'              => $this->department,
            'years_of_experience'     => $this->yearsOfExperience,
            'salary'                  => $this->salary,
            'emergency_contact_name'  => $this->emergencyContactName,
            'emergency_contact_phone' => $this->emergencyContactPhone,
        ];
        return array_filter($map, static fn ($v) => $v !== null);
    }
}
