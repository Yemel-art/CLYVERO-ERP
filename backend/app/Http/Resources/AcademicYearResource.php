<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AcademicYear */
class AcademicYearResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'start_date' => $this->start_date->toDateString(),
            'end_date'   => $this->end_date->toDateString(),
            'status'     => $this->status,
            'school_id'  => $this->school_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'terms_count' => $this->whenCounted('terms'),
            'classes_count' => $this->whenCounted('classes'),
        ];
    }
}
