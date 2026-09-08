<?php

declare(strict_types=1);

namespace App\Http\Requests\ParentGuardian;

use App\DTO\ParentGuardian\ParentGuardianDTO;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\ParentGuardian::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name'     => ['required', 'string', 'max:80'],
            'last_name'      => ['required', 'string', 'max:80'],
            'middle_name'    => ['nullable', 'string', 'max:80'],
            'gender'         => ['required', Rule::enum(Gender::class)],
            'email'          => ['required', 'email', 'max:120', Rule::unique('parents', 'email')->where('school_id', $this->user()?->school_id)->whereNull('deleted_at')],
            'phone'          => ['required', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'address'        => ['nullable', 'string', 'max:500'],
            'city'           => ['nullable', 'string', 'max:80'],
            'country'        => ['nullable', 'string', 'max:80'],
            'occupation'     => ['nullable', 'string', 'max:120'],
            'workplace'      => ['nullable', 'string', 'max:160'],
            'national_id'    => ['nullable', 'string', 'max:60'],
            'is_active'      => ['sometimes', 'boolean'],
            'create_user_account' => ['sometimes', 'boolean'],

            'children'                  => ['array'],
            'children.*.student_id'     => ['required_with:children', 'uuid', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'children.*.relationship'   => ['required_with:children', 'string', 'max:60'],
            'children.*.is_primary'     => ['sometimes', 'boolean'],
            'children.*.can_pickup'     => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(): ParentGuardianDTO
    {
        /** @var array<int, array{student_id:string, relationship:string, is_primary?:bool, can_pickup?:bool}> $children */
        $children = $this->input('children', []) ?? [];

        return new ParentGuardianDTO(
            firstName:      $this->string('first_name')->toString(),
            lastName:       $this->string('last_name')->toString(),
            gender:         Gender::from($this->string('gender')->toString()),
            email:          strtolower($this->string('email')->toString()),
            phone:          $this->string('phone')->toString(),
            middleName:     $this->input('middle_name'),
            alternatePhone: $this->input('alternate_phone'),
            address:        $this->input('address'),
            city:           $this->input('city'),
            country:        $this->input('country'),
            occupation:     $this->input('occupation'),
            workplace:      $this->input('workplace'),
            nationalId:     $this->input('national_id'),
            isActive:       $this->boolean('is_active', true),
            createUserAccount: $this->boolean('create_user_account', true),
            children:       $children,
        );
    }
}
