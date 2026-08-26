<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Term */
class TermResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'name'             => $this->name,
            'sequence'         => $this->sequence,
            'start_date'       => $this->start_date->toDateString(),
            'end_date'         => $this->end_date->toDateString(),
            'status'           => $this->status,
            'academic_year'    => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id, 'title' => $this->academicYear->title,
            ]),
        ];
    }
}
