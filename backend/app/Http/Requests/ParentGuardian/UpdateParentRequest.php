<?php

declare(strict_types=1);

namespace App\Http\Requests\ParentGuardian;

use App\DTO\ParentGuardian\ParentGuardianDTO;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('parent')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $parentId = $this->route('parent')?->id;
        return [
            'first_name'     => ['sometimes', 'required', 'string', 'max:80'],
            'last_name'      => ['sometimes', 'required', 'string', 'max:80'],
            'middle_name'    => ['nullable', 'string', 'max:80'],
            'gender'         => ['sometimes', 'required', Rule::enum(Gender::class)],
            'email'          => ['sometimes', 'required', 'email', 'max:120',
                Rule::unique('parents', 'email')->where('school_id', $this->user()?->school_id)->ignore($parentId)->whereNull('deleted_at'),
            ],
            'phone'          => ['sometimes', 'required', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'address'        => ['nullable', 'string', 'max:500'],
            'city'           => ['nullable', 'string', 'max:80'],
            'country'        => ['nullable', 'string', 'max:80'],
            'occupation'     => ['nullable', 'string', 'max:120'],
            'workplace'      => ['nullable', 'string', 'max:160'],
            'national_id'    => ['nullable', 'string', 'max:60'],
            'is_active'      => ['sometimes', 'boolean'],

            'children'                  => ['sometimes', 'array'],
            'children.*.student_id'     => ['required_with:children', 'uuid', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'children.*.relationship'   => ['required_with:children', 'string', 'max:60'],
            'children.*.is_primary'     => ['sometimes', 'boolean'],
            'children.*.can_pickup'     => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(): ParentGuardianDTO
    {
        $p = $this->route('parent');
        /** @var array<int, array{student_id:string, relationship:string, is_primary?:bool, can_pickup?:bool}> $children */
        $children = $this->input('children', []) ?? [];

        return new ParentGuardianDTO(
            firstName:      $this->input('first_name', $p->first_name),
            lastName:       $this->input('last_name', $p->last_name),
            gender:         Gender::from($this->input('gender', $p->gender->value)),
            email:          strtolower($this->input('email', $p->email)),
            phone:          $this->input('phone', $p->phone),
            middleName:     $this->input('middle_name', $p->middle_name),
            alternatePhone: $this->input('alternate_phone', $p->alternate_phone),
            address:        $this->input('address', $p->address),
            city:           $this->input('city', $p->city),
            country:        $this->input('country', $p->country),
            occupation:     $this->input('occupation', $p->occupation),
            workplace:      $this->input('workplace', $p->workplace),
            nationalId:     $this->input('national_id', $p->national_id),
            isActive:       $this->boolean('is_active', $p->is_active),
            createUserAccount: false,
            children:       $children,
        );
    }
}
