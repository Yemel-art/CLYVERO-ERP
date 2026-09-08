<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentEnrollment
 */
final class StudentEnrollmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $decision = $this->academicDecision;

        return [
            'id' => $this->id,
            'academic_year' => [
                'id' => $this->academicYear->id,
                'title' => $this->academicYear->title,
                'status' => $this->academicYear->status,
            ],
            'class' => $this->schoolClass ? [
                'id' => $this->schoolClass->id,
                'name' => $this->schoolClass->name,
                'grade_level' => $this->schoolClass->grade_level,
            ] : null,
            'enrolled_at' => $this->enrolled_at->toDateString(),
            'status' => $this->status->value,
            'decision' => $decision ? [
                'status' => $decision->final_decision->value,
                'label' => $decision->final_decision->label($locale),
                'final_average' => $decision->final_average,
                'teacher_appreciation' => $decision->teacher_appreciation,
                'class_council_recommendation' => $decision->class_council_recommendation,
                'finalized_at' => $decision->finalized_at?->toIso8601String(),
            ] : null,
        ];
    }
}
