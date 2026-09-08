<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ParentGuardian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ParentGuardian
 */
class ParentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'first_name'       => $this->first_name,
            'last_name'        => $this->last_name,
            'middle_name'      => $this->middle_name,
            'full_name'        => $this->full_name,
            'gender'           => $this->gender->value,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'alternate_phone'  => $this->alternate_phone,
            'address'          => $this->address,
            'city'             => $this->city,
            'country'          => $this->country,
            'occupation'       => $this->occupation,
            'workplace'        => $this->workplace,
            'national_id'      => $this->when(
                $request->user()?->isAdministrator(),
                fn () => $this->national_id,
            ),
            'is_active'        => $this->is_active,
            'temporary_password' => $this->when(
                $this->resource->getAttribute('temporary_password') !== null,
                fn () => $this->resource->getAttribute('temporary_password'),
            ),

            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id'            => $this->user->id,
                'email'         => $this->user->email,
                'is_active'     => $this->user->is_active,
                'last_login_at' => $this->user->last_login_at?->toIso8601String(),
            ] : null),

            'children' => $this->whenLoaded('students', fn () => $this->students->map(fn ($s) => [
                'id'                => $s->id,
                'admission_number'  => $s->admission_number,
                'full_name'         => $s->full_name,
                'photo_url'         => $s->photo_url,
                'status'            => $s->status->value,
                'pivot' => [
                    'relationship' => $s->pivot->relationship,
                    'is_primary'   => (bool) $s->pivot->is_primary,
                    'can_pickup'   => (bool) $s->pivot->can_pickup,
                ],
            ])->all()),

            'children_count' => $this->whenLoaded('students', fn () => $this->students->count()),

            'created_at'  => $this->created_at?->toIso8601String(),
            'archived_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
