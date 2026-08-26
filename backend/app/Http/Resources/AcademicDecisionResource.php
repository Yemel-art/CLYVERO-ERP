<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AcademicDecision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AcademicDecision */
final class AcademicDecisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn (): array => [
                'id' => $this->student->id,
                'full_name' => $this->student->full_name,
                'admission_number' => $this->student->admission_number,
            ]),
            'class' => $this->whenLoaded('enrollment', fn (): ?array => $this->enrollment->schoolClass ? [
                'id' => $this->enrollment->schoolClass->id,
                'name' => $this->enrollment->schoolClass->name,
            ] : null),
            'academic_year_id' => $this->academic_year_id,
            'computed_decision' => $this->computed_decision->value,
            'final_decision' => $this->final_decision->value,
            'decision_label' => $this->final_decision->label($locale),
            'final_average' => $this->final_average !== null ? (float) $this->final_average : null,
            'failed_subjects_count' => $this->failed_subjects_count,
            'failed_subject_ids' => $this->failed_subject_ids ?? [],
            'decision_reasons' => $this->decision_reasons ?? [],
            'teacher_appreciation' => $this->teacher_appreciation,
            'class_council_recommendation' => $this->class_council_recommendation,
            'override_reason' => $this->override_reason,
            'overridden_at' => $this->overridden_at?->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
        ];
    }
}
