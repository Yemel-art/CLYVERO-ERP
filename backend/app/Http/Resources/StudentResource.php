<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_number' => $this->admission_number,
            'official_matricule' => $this->official_matricule,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'full_name' => $this->full_name,
            'initials' => $this->initials,
            'gender' => $this->gender?->value,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,
            'place_of_birth' => $this->place_of_birth,
            'nationality' => $this->nationality,
            'religion' => $this->religion,
            'photo_url' => $this->photo_url,

            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,

            'parent_id' => $this->parent_id,
            'primary_parent' => $this->whenLoaded('primaryParent', fn () => $this->primaryParent ? [
                'id' => $this->primaryParent->id,
                'full_name' => $this->primaryParent->full_name,
                'email' => $this->primaryParent->email,
                'phone' => $this->primaryParent->phone,
                'has_portal' => $this->primaryParent->user_id !== null,
            ] : null),
            'parent_credentials' => $this->when(
                $this->resource->getAttribute('parent_credentials') !== null,
                fn () => $this->resource->getAttribute('parent_credentials'),
            ),
            'class_id' => $this->class_id,
            'class' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass ? [
                'id' => $this->schoolClass->id,
                'name' => $this->schoolClass->name,
                'grade_level' => $this->schoolClass->grade_level,
            ] : null),
            'academic_year_id' => $this->academic_year_id,
            'enrollment_date' => $this->enrollment_date->toDateString(),
            'previous_school' => $this->previous_school,
            'cycle' => $this->cycle?->value ?? 'secondary_general',
            'cycle_label' => $this->cycle?->label() ?? 'Secondary General',
            'speciality' => $this->speciality,
            'speciality_label' => $this->speciality
                ? (config("student.specialities.{$this->speciality}.name") ?? $this->speciality)
                : null,

            'emergency_contact' => [
                'name' => $this->emergency_contact_name,
                'phone' => $this->emergency_contact_phone,
                'relationship' => $this->emergency_contact_relationship,
            ],

            'health' => [
                'blood_group' => $this->blood_group,
                'allergies' => $this->allergies,
                'medical_conditions' => $this->medical_conditions,
            ],

            'status' => $this->status->value,
            'status_label' => $this->status->displayName(),

            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'title' => $this->academicYear->title,
            ]),

            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'full_name' => $this->createdBy->full_name,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'archived_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
