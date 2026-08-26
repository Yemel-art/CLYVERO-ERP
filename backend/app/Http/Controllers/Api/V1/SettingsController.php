<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\School;
use App\Services\Platform\SchoolBrandingService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SettingsController extends ApiController
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly SchoolBrandingService $branding,
    ) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('settings.view'), 403);
        $school = $this->schoolFor($request);

        return $this->ok([
            'school' => [
                'id' => $school->id,
                'name' => $school->school_name,
                'slug' => $school->slug,
                'school_code' => $school->school_code,
                'address' => $school->address,
                'phone' => $school->phone,
                'email' => $school->email,
                'motto' => $school->slogan,
                'logo_url' => $school->logo_url ?? null,
                'secondary_logo_url' => $school->secondary_logo_url ?? null,
                'document_header_image_url' => $school->document_header_image_url ?? null,
                'document_header_image_settings' => $school->documentHeaderImageSettings(),
                'currency' => $school->currency ?? 'XAF',
                'default_locale' => $school->default_locale ?? 'fr',
                'report_card_remarks' => $school->report_card_remarks,
                'honor_roll_rules' => $school->honor_roll_rules,
                'primary_color' => $school->primary_color,
                'secondary_color' => $school->secondary_color,
                'document_header' => $school->document_header,
                'document_footer' => $school->document_footer,
                'principal_name' => $school->principal_name,
                'principal_title' => $school->principal_title,
                'education_systems' => $school->education_systems ?? [],
            ],
            'app' => [
                'name' => config('app.name', 'Clyvero ERP'),
                'version' => '1.0.0',
                'env' => config('app.env'),
            ],
        ], 'Settings retrieved.');
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('settings.edit'), 403);
        $school = $this->schoolFor($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:60',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('schools', 'slug')->ignore($school->id),
            ],
            'school_code' => [
                'sometimes', 'required', 'string', 'max:32', 'regex:/^[A-Z0-9][A-Z0-9-]*$/',
                Rule::unique('schools', 'school_code')->ignore($school->id),
            ],
            'address' => ['sometimes', 'nullable', 'string', 'max:300'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:120'],
            'motto' => ['sometimes', 'nullable', 'string', 'max:200'],
            'default_locale' => ['sometimes', 'required', Rule::in(['fr', 'en'])],
            'report_card_remarks' => ['sometimes', 'array', 'min:1'],
            'report_card_remarks.*.minimum' => ['required', 'numeric', 'min:0', 'max:20'],
            'report_card_remarks.*.fr' => ['required', 'string', 'max:120'],
            'report_card_remarks.*.en' => ['required', 'string', 'max:120'],
            'honor_roll_rules' => ['sometimes', 'array', 'min:1'],
            'honor_roll_rules.*.minimum' => ['required', 'numeric', 'min:0', 'max:20'],
            'honor_roll_rules.*.max_rank' => ['nullable', 'integer', 'min:1', 'max:500'],
            'honor_roll_rules.*.fr' => ['required', 'string', 'max:120'],
            'honor_roll_rules.*.en' => ['required', 'string', 'max:120'],
            'primary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'document_header' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'document_header_image_settings' => ['sometimes', 'array'],
            'document_header_image_settings.mode' => ['required_with:document_header_image_settings', Rule::in(['fit', 'full_width'])],
            'document_header_image_settings.width' => ['required_with:document_header_image_settings', 'integer', 'min:30', 'max:100'],
            'document_header_image_settings.max_height' => ['required_with:document_header_image_settings', 'integer', 'min:40', 'max:200'],
            'document_header_image_settings.alignment' => ['required_with:document_header_image_settings', Rule::in(['left', 'center', 'right'])],
            'document_footer' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'principal_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'principal_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'education_systems' => ['sometimes', 'array', 'min:1'],
            'education_systems.*' => [Rule::in(['secondary_general', 'secondary_technical'])],
        ]);

        $school->update([
            'school_name' => $validated['name'] ?? $school->school_name,
            'slug' => $validated['slug'] ?? $school->slug,
            'school_code' => isset($validated['school_code']) ? strtoupper($validated['school_code']) : $school->school_code,
            'address' => $validated['address'] ?? $school->address,
            'phone' => $validated['phone'] ?? $school->phone,
            'email' => $validated['email'] ?? $school->email,
            'slogan' => $validated['motto'] ?? $school->slogan,
            'default_locale' => $validated['default_locale'] ?? $school->default_locale,
            'report_card_remarks' => $validated['report_card_remarks'] ?? $school->report_card_remarks,
            'honor_roll_rules' => $validated['honor_roll_rules'] ?? $school->honor_roll_rules,
            'primary_color' => $validated['primary_color'] ?? $school->primary_color,
            'secondary_color' => $validated['secondary_color'] ?? $school->secondary_color,
            'document_header' => $validated['document_header'] ?? $school->document_header,
            'document_header_image_settings' => $validated['document_header_image_settings'] ?? $school->document_header_image_settings,
            'document_footer' => $validated['document_footer'] ?? $school->document_footer,
            'principal_name' => $validated['principal_name'] ?? $school->principal_name,
            'principal_title' => $validated['principal_title'] ?? $school->principal_title,
            'education_systems' => $validated['education_systems'] ?? $school->education_systems,
        ]);

        return $this->ok([
            'id' => $school->id,
            'name' => $school->school_name,
            'slug' => $school->slug,
            'school_code' => $school->school_code,
            'address' => $school->address,
            'phone' => $school->phone,
            'email' => $school->email,
            'motto' => $school->slogan,
            'logo_url' => $school->logo_url ?? null,
            'secondary_logo_url' => $school->secondary_logo_url ?? null,
            'document_header_image_url' => $school->document_header_image_url ?? null,
            'document_header_image_settings' => $school->documentHeaderImageSettings(),
            'currency' => $school->currency ?? 'XAF',
            'default_locale' => $school->default_locale ?? 'fr',
            'report_card_remarks' => $school->report_card_remarks,
            'honor_roll_rules' => $school->honor_roll_rules,
            'primary_color' => $school->primary_color,
            'secondary_color' => $school->secondary_color,
            'document_header' => $school->document_header,
            'document_footer' => $school->document_footer,
            'principal_name' => $school->principal_name,
            'principal_title' => $school->principal_title,
            'education_systems' => $school->education_systems ?? [],
        ], 'School settings updated.');
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('settings.edit'), 403);
        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'kind' => ['sometimes', Rule::in(['primary', 'secondary', 'document_header'])],
        ]);
        $school = $this->schoolFor($request);
        $kind = $validated['kind'] ?? 'primary';
        $fresh = $this->branding->uploadLogo($school, $request->file('logo'), $kind);
        return $this->ok([
            'logo_url' => $fresh->logo_url,
            'secondary_logo_url' => $fresh->secondary_logo_url,
            'document_header_image_url' => $fresh->document_header_image_url,
            'document_header_image_settings' => $fresh->documentHeaderImageSettings(),
        ], 'School logo updated.');
    }

    private function schoolFor(Request $request): School
    {
        $schoolId = $this->tenant->schoolId();
        abort_unless($schoolId, 404, 'No school is associated with this account.');

        return School::query()->whereKey($schoolId)->firstOrFail();
    }
}
