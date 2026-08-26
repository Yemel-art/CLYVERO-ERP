<?php

declare(strict_types=1);

namespace App\DTO\Student;

use App\Enums\Gender;
use Carbon\CarbonImmutable;

/**
 * Carries student data from Form Requests into the Service layer.
 *
 * All fields except identity essentials are optional so the same DTO
 * works for both creation and partial update.
 */
final readonly class StudentDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public Gender $gender,
        public CarbonImmutable $dateOfBirth,
        public CarbonImmutable $enrollmentDate,

        public ?string $admissionNumber        = null,
        public ?string $middleName            = null,
        public ?string $placeOfBirth          = null,
        public ?string $nationality           = null,
        public ?string $religion              = null,

        public ?string $email                 = null,
        public ?string $phone                 = null,
        public ?string $address               = null,
        public ?string $city                  = null,
        public ?string $country               = null,

        public ?string $parentId              = null,
        public ?string $classId               = null,
        public ?string $academicYearId        = null,
        public ?string $previousSchool        = null,

        // ─── Cycle & speciality ──────────────────────────────────────
        public ?string $cycle                 = null,
        public ?string $speciality            = null,

        public ?string $emergencyContactName         = null,
        public ?string $emergencyContactPhone        = null,
        public ?string $emergencyContactRelationship = null,

        public ?string $bloodGroup            = null,
        public ?string $allergies             = null,
        public ?string $medicalConditions     = null,
    ) {
    }

    /**
     * Project the DTO to an array suitable for Eloquent mass assignment.
     * Returns only fields that are non-null so callers can use it for
     * partial updates without overwriting existing values with nulls.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $map = [
            'admission_number'                  => $this->admissionNumber,
            'first_name'                       => $this->firstName,
            'last_name'                        => $this->lastName,
            'gender'                           => $this->gender->value,
            'date_of_birth'                    => $this->dateOfBirth->toDateString(),
            'enrollment_date'                  => $this->enrollmentDate->toDateString(),
            'middle_name'                      => $this->middleName,
            'place_of_birth'                   => $this->placeOfBirth,
            'nationality'                      => $this->nationality,
            'religion'                         => $this->religion,
            'email'                            => $this->email,
            'phone'                            => $this->phone,
            'address'                          => $this->address,
            'city'                             => $this->city,
            'country'                          => $this->country,
            'parent_id'                        => $this->parentId,
            'class_id'                         => $this->classId,
            'academic_year_id'                 => $this->academicYearId,
            'previous_school'                  => $this->previousSchool,
            'cycle'                            => $this->cycle,
            'speciality'                       => $this->speciality,
            'emergency_contact_name'           => $this->emergencyContactName,
            'emergency_contact_phone'          => $this->emergencyContactPhone,
            'emergency_contact_relationship'   => $this->emergencyContactRelationship,
            'blood_group'                      => $this->bloodGroup,
            'allergies'                        => $this->allergies,
            'medical_conditions'               => $this->medicalConditions,
        ];

        return array_filter($map, static fn ($v) => $v !== null);
    }
}
