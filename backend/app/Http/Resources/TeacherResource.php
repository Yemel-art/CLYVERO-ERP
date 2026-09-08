<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Teacher;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Teacher
 */
class TeacherResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isAdminOrSelf = $request->user()?->isAdministrator()
            || ($request->user()?->id && $this->user_id === $request->user()->id);
        $subjectNames = collect();
        if ($this->relationLoaded('classesTaught')) {
            $subjectNames = Subject::query()
                ->whereIn('id', $this->classesTaught->pluck('pivot.subject_id')->filter()->unique())
                ->pluck('name', 'id');
        }

        return [
            'id'               => $this->id,
            'employee_number'  => $this->employee_number,
            'first_name'       => $this->first_name,
            'last_name'        => $this->last_name,
            'middle_name'      => $this->middle_name,
            'full_name'        => $this->full_name,
            'gender'           => $this->gender->value,
            'date_of_birth'    => $this->date_of_birth?->toDateString(),
            'nationality'      => $this->nationality,
            'photo_url'        => $this->photo_url,

            'email'   => $this->email,
            'phone'   => $this->phone,
            'address' => $this->address,
            'city'    => $this->city,
            'country' => $this->country,

            'qualification'       => $this->qualification,
            'specialization'      => $this->specialization,
            'position'            => $this->position,
            'department'          => $this->department,
            'years_of_experience' => $this->years_of_experience,
            'hire_date'           => $this->hire_date->toDateString(),
            // Salary is sensitive — only administrators and the teacher themselves see it.
            'salary'              => $this->when($isAdminOrSelf, fn () => $this->salary !== null ? (float) $this->salary : null),

            'emergency_contact' => [
                'name'  => $this->emergency_contact_name,
                'phone' => $this->emergency_contact_phone,
            ],

            'status'        => $this->status->value,
            'status_label'  => $this->status->displayName(),

            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id'        => $this->user->id,
                'email'     => $this->user->email,
                'is_active' => $this->user->is_active,
                'last_login_at' => $this->user->last_login_at?->toIso8601String(),
            ] : null),

            'teaching_assignments' => $this->whenLoaded('classesTaught', fn () => $this->classesTaught
                ->map(fn ($class) => [
                    'class_id' => $class->id,
                    'class_name' => $class->name,
                    'grade_level' => $class->grade_level,
                    'speciality' => $class->speciality,
                    'cycle' => $class->cycle,
                    'language' => $class->language,
                    'subject_id' => $class->pivot->subject_id,
                    'subject_name' => $subjectNames->get($class->pivot->subject_id, 'Subject'),
                    'coefficient' => (float) $class->pivot->coefficient,
                    'weekly_frequency' => $class->pivot->weekly_frequency === null
                        ? null
                        : (int) $class->pivot->weekly_frequency,
                    'academic_year' => $class->academicYear?->title,
                ])
                ->unique(fn (array $assignment) => $assignment['class_id'] . '|' . $assignment['subject_id'])
                ->values()
                ->all()),
            'form_master_classes' => $this->whenLoaded('formMasterOf', fn () => $this->formMasterOf
                ->map(fn ($class) => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'grade_level' => $class->grade_level,
                    'speciality' => $class->speciality,
                    'cycle' => $class->cycle,
                    'language' => $class->language,
                    'academic_year' => $class->academicYear?->title,
                ])->values()->all()),

            'created_at'  => $this->created_at?->toIso8601String(),
            'archived_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
