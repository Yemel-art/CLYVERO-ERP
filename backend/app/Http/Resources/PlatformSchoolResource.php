<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PlatformSchoolResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_code' => $this->school_code,
            'slug' => $this->slug,
            'school_name' => $this->school_name,
            'slogan' => $this->slogan,
            'logo_url' => $this->logo_url,
            'secondary_logo_url' => $this->secondary_logo_url,
            'document_header_image_url' => $this->document_header_image_url,
            'document_header_image_settings' => $this->documentHeaderImageSettings(),
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'website' => $this->website,
            'city' => $this->city,
            'country' => $this->country,
            'default_locale' => $this->default_locale,
            'education_systems' => $this->education_systems ?? [],
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'document_header' => $this->document_header,
            'document_footer' => $this->document_footer,
            'principal_name' => $this->principal_name,
            'principal_title' => $this->principal_title,
            'is_active' => $this->is_active,
            'current_academic_year' => $this->whenLoaded('currentAcademicYear', fn () => $this->currentAcademicYear ? [
                'id' => $this->currentAcademicYear->id,
                'title' => $this->currentAcademicYear->title,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
