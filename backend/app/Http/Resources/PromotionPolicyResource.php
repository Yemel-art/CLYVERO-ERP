<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PromotionPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PromotionPolicy */
final class PromotionPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'name' => $this->name,
            'grading_scale' => (float) $this->grading_scale,
            'passing_average' => (float) $this->passing_average,
            'minimum_subject_mark' => (float) $this->minimum_subject_mark,
            'maximum_failed_subjects' => $this->maximum_failed_subjects,
            'critical_subject_ids' => $this->critical_subject_ids ?? [],
            'failure_conditions' => $this->failure_conditions ?? [],
            'exclusion_conditions' => $this->exclusion_conditions ?? [],
            'allow_class_council_override' => $this->allow_class_council_override,
            'is_active' => $this->is_active,
        ];
    }
}
