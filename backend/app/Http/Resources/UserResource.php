<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'full_name'  => $this->full_name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'avatar'     => $this->avatar,
            'is_active'  => $this->is_active,
            'two_factor_enabled' => $this->two_factor_enabled,
            'last_login_at'      => $this->last_login_at?->toIso8601String(),
            'school' => $this->when(
                $this->relationLoaded('school') && $this->school !== null,
                fn () => [
                    'id' => $this->school->id,
                    'slug' => $this->school->slug,
                    'school_code' => $this->school->school_code,
                    'name' => $this->school->school_name,
                    'logo_url' => $this->school->logo_url,
                    'default_locale' => $this->school->default_locale,
                ],
            ),
            'role'       => $this->whenLoaded('role', fn () => [
                'id'           => $this->role->id,
                'name'         => $this->role->name,
                'display_name' => $this->role->display_name,
                'permissions'  => $this->role->relationLoaded('permissions')
                    ? $this->role->permissions->pluck('name')->all()
                    : [],
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
