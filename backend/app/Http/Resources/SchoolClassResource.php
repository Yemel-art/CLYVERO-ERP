<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SchoolClass */
class SchoolClassResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'grade_level' => $this->grade_level,
            'speciality' => $this->speciality,
            'speciality_label' => $this->speciality ? $this->specialityLabel() : null,
            'cycle' => $this->cycle,
            'cycle_label' => $this->cycle ? (SchoolClass::CYCLES[$this->cycle] ?? $this->cycle) : null,
            'language' => $this->language,
            'capacity' => $this->capacity,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'academic_year_id' => $this->academic_year_id,
            'education_system' => $this->education_system,
            'form_master_id' => $this->form_master_id,

            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id, 'title' => $this->academicYear->title,
            ]),
            'form_master' => $this->whenLoaded('formMaster', fn () => $this->formMaster ? [
                'id' => $this->formMaster->id, 'full_name' => $this->formMaster->full_name,
            ] : null),
            'subjects' => $this->whenLoaded('subjects', fn () => $this->subjects->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'color' => $s->color,
                'pivot' => [
                    'teacher_id' => $s->pivot->teacher_id,
                    'coefficient' => (float) $s->pivot->coefficient,
                    'weekly_frequency' => (int) $s->pivot->weekly_frequency,
                ],
            ])->all()),
            'students' => $this->whenLoaded('students', fn () => $this->students->map(fn ($student) => [
                'id' => $student->id,
                'admission_number' => $student->admission_number,
                'official_matricule' => $student->official_matricule,
                'full_name' => $student->full_name,
                'import_name' => trim(implode(' ', array_filter([$student->last_name, $student->first_name, $student->middle_name]))),
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'place_of_birth' => $student->place_of_birth,
                'photo_url' => $student->photo_url,
                'gender' => $student->gender->value,
                'status' => $student->status->value,
                'status_label' => $student->status->displayName(),
            ])->all()),

            'students_count' => $this->whenCounted('students'),
            'archived_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    private function specialityLabel(): string
    {
        $catalog = $this->education_system === 'secondary_general' ? 'general_streams' : 'specialities';

        return sprintf(
            '%s (%s)',
            strtoupper((string) config("student.{$catalog}.{$this->speciality}.name_en", $this->speciality)),
            (string) config("student.{$catalog}.{$this->speciality}.name", $this->speciality),
        );
    }
}
