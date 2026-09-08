<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FeeStructure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeeStructure */
class FeeStructureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'class_id'         => $this->class_id,
            'name'             => $this->name,
            'category'         => $this->category,
            'amount'           => (float) $this->amount,
            'frequency'        => $this->frequency,
            'is_required'      => $this->is_required,
            'description'      => $this->description,
            'school_class'     => $this->whenLoaded('schoolClass', fn () => $this->schoolClass ? [
                'id' => $this->schoolClass->id, 'name' => $this->schoolClass->name,
            ] : null),
        ];
    }
}
