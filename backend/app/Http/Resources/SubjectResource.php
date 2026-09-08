<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subject */
class SubjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'code'        => $this->code,
            'education_system' => $this->education_system,
            'coefficient' => (float) $this->coefficient,
            'color'       => $this->color,
            'description' => $this->description,
            'is_active'   => $this->is_active,
            'archived_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
