<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Platform\StoreSchoolRequest;
use App\Http\Resources\PlatformSchoolResource;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\Platform\SchoolProvisioningService;
use App\Services\Platform\SchoolBrandingService;
use App\Rules\StrongPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PlatformSchoolController extends ApiController
{
    public function __construct(
        private readonly SchoolProvisioningService $schools,
        private readonly SchoolBrandingService $branding,
    ) {}

    public function overview(): JsonResponse
    {
        $recentSchools = School::query()
            ->with('currentAcademicYear')
            ->latest('created_at')
            ->limit(5)
            ->get();

        return $this->ok([
            'schools_total' => School::query()->count(),
            'schools_active' => School::query()->where('is_active', true)->count(),
            'schools_inactive' => School::query()->where('is_active', false)->count(),
            'school_administrators' => User::withoutGlobalScopes()
                ->whereNotNull('school_id')
                ->whereHas('role', fn ($query) => $query->where('name', UserRole::Administrator->value))
                ->count(),
            'students_total' => Student::withoutGlobalScopes()->count(),
            'teachers_total' => Teacher::withoutGlobalScopes()->count(),
            'recent_schools' => PlatformSchoolResource::collection($recentSchools),
        ], 'Platform overview retrieved.');
    }

    public function index(Request $request): JsonResponse
    {
        $page = School::query()
            ->with('currentAcademicYear')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->input('q')).'%';
                $query->where(fn ($match) => $match
                    ->where('school_name', 'ilike', $like)
                    ->orWhere('school_code', 'ilike', $like));
            })
            ->orderBy('school_name')
            ->paginate(min(max((int) $request->integer('per_page', 25), 1), 100));

        return $this->ok(PlatformSchoolResource::collection($page->items()), 'Schools retrieved.', meta: [
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function store(StoreSchoolRequest $request): JsonResponse
    {
        return $this->created(
            new PlatformSchoolResource($this->schools->create($request->validated())),
            'School and administrator account created successfully.',
        );
    }

    public function update(Request $request, School $school): JsonResponse
    {
        $validated = $request->validate([
            'school_name' => ['sometimes', 'required', 'string', 'max:120'],
            'school_code' => ['sometimes', 'required', 'string', 'max:32', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', Rule::unique('schools', 'school_code')->ignore($school->id)],
            'slug' => ['sometimes', 'required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('schools', 'slug')->ignore($school->id)],
            'slogan' => ['sometimes', 'nullable', 'string', 'max:200'],
            'email' => ['sometimes', 'nullable', 'email', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:300'],
            'website' => ['sometimes', 'nullable', 'url', 'max:200'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'country' => ['sometimes', 'nullable', 'string', 'max:120'],
            'default_locale' => ['sometimes', 'required', Rule::in(['fr', 'en'])],
            'education_systems' => ['sometimes', 'array', 'min:1'],
            'education_systems.*' => [Rule::in(['secondary_general', 'secondary_technical'])],
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
        ]);

        if (isset($validated['school_code'])) {
            $validated['school_code'] = strtoupper($validated['school_code']);
        }

        return $this->ok(
            new PlatformSchoolResource($this->branding->update($school, $validated)),
            'School identity updated.',
        );
    }

    public function uploadLogo(Request $request, School $school): JsonResponse
    {
        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'kind' => ['sometimes', Rule::in(['primary', 'secondary', 'document_header'])],
        ]);

        return $this->ok(
            new PlatformSchoolResource($this->branding->uploadLogo(
                $school,
                $request->file('logo'),
                $validated['kind'] ?? 'primary',
            )),
            'School logo updated.',
        );
    }

    public function updateStatus(Request $request, School $school): JsonResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $updated = $this->schools->setActive($school, (bool) $validated['is_active']);

        return $this->ok(
            new PlatformSchoolResource($updated),
            $updated->is_active ? 'School activated.' : 'School deactivated and active sessions revoked.',
        );
    }

    public function administratorCredentials(School $school): JsonResponse
    {
        return $this->ok(
            $this->administratorPayload($this->schools->administratorFor($school)),
            'School administrator credentials retrieved.',
        );
    }

    public function updateAdministratorCredentials(Request $request, School $school): JsonResponse
    {
        $administrator = $this->schools->administratorFor($school);
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => [
                'required',
                'email',
                'max:160',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => $query
                        ->where('school_id', $school->id)
                        ->whereNull('deleted_at'))
                    ->ignore($administrator->id),
            ],
            'administrator_password' => ['nullable', 'string', 'confirmed', new StrongPassword()],
        ]);

        $updated = $this->schools->updateAdministratorCredentials($school, $validated);

        return $this->ok(
            $this->administratorPayload($updated),
            'Principal login credentials updated and existing sessions revoked.',
        );
    }

    /** @return array<string, mixed> */
    private function administratorPayload(User $administrator): array
    {
        return [
            'id' => $administrator->id,
            'first_name' => $administrator->first_name,
            'last_name' => $administrator->last_name,
            'full_name' => $administrator->full_name,
            'email' => $administrator->email,
            'is_active' => $administrator->is_active,
            'last_login_at' => $administrator->last_login_at?->toIso8601String(),
        ];
    }
}
