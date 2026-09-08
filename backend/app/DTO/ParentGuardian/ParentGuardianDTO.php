<?php

declare(strict_types=1);

namespace App\DTO\ParentGuardian;

use App\Enums\Gender;

final readonly class ParentGuardianDTO
{
    /**
     * @param array<int, array{student_id:string, relationship:string, is_primary?:bool, can_pickup?:bool}> $children
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public Gender $gender,
        public string $email,
        public string $phone,

        public ?string $middleName     = null,
        public ?string $alternatePhone = null,
        public ?string $address        = null,
        public ?string $city           = null,
        public ?string $country        = null,
        public ?string $occupation     = null,
        public ?string $workplace      = null,
        public ?string $nationalId     = null,
        public bool    $isActive       = true,
        public bool    $createUserAccount = true,
        public array   $children       = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        $map = [
            'first_name'      => $this->firstName,
            'last_name'       => $this->lastName,
            'gender'          => $this->gender->value,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'middle_name'     => $this->middleName,
            'alternate_phone' => $this->alternatePhone,
            'address'         => $this->address,
            'city'            => $this->city,
            'country'         => $this->country,
            'occupation'      => $this->occupation,
            'workplace'       => $this->workplace,
            'national_id'     => $this->nationalId,
            'is_active'       => $this->isActive,
        ];
        return array_filter($map, static fn ($v) => $v !== null);
    }
}
