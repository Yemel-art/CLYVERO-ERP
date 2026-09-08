<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\ParentGuardian\AttachChildRequest;
use App\Http\Requests\ParentGuardian\StoreParentRequest;
use App\Http\Requests\ParentGuardian\UpdateParentRequest;
use App\Http\Resources\ParentResource;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Services\ParentGuardian\ParentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ParentController extends ApiController
{
    public function __construct(
        private readonly ParentService $parents,
    ) {
        $this->authorizeResource(ParentGuardian::class, 'parent');
    }

    public function index(Request $request): JsonResponse
    {
        $page = $this->parents->list(
            filters:   $request->only(['q', 'gender', 'is_active', 'has_children', 'include_archived']),
            with:      ['user', 'students'],
            perPage:   (int) $request->integer('per_page', 20),
            sortBy:    (string) $request->input('sort', 'last_name'),
            sortOrder: (string) $request->input('order', 'asc'),
        );

        return $this->ok(
            data: ParentResource::collection($page->items()),
            message: 'Parents retrieved successfully.',
            meta: [
                'page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'last_page' => $page->lastPage(),
            ],
        );
    }

    public function show(ParentGuardian $parent): JsonResponse
    {
        return $this->ok(
            data: new ParentResource($parent->load(['user', 'students'])),
            message: 'Parent retrieved successfully.',
        );
    }

    public function store(StoreParentRequest $request): JsonResponse
    {
        $parent = $this->parents->register($request->toDTO());
        return $this->created(new ParentResource($parent), 'Parent registered successfully.');
    }

    public function update(UpdateParentRequest $request, ParentGuardian $parent): JsonResponse
    {
        return $this->ok(
            data: new ParentResource($this->parents->update($parent, $request->toDTO())),
            message: 'Parent updated successfully.',
        );
    }

    public function archive(Request $request, ParentGuardian $parent): JsonResponse
    {
        $this->authorize('archive', $parent);
        return $this->ok(
            data: new ParentResource($this->parents->archive($parent)),
            message: 'Parent archived successfully.',
        );
    }

    public function restore(Request $request, string $parentId): JsonResponse
    {
        /** @var ParentGuardian $parent */
        $parent = ParentGuardian::withTrashed()->findOrFail($parentId);
        $this->authorize('restore', $parent);
        return $this->ok(
            data: new ParentResource($this->parents->restore($parent)),
            message: 'Parent restored successfully.',
        );
    }

    public function attachChild(AttachChildRequest $request, ParentGuardian $parent): JsonResponse
    {
        /** @var Student $student */
        $student = Student::findOrFail($request->input('student_id'));
        $updated = $this->parents->attachChild($parent, $student, [
            'relationship' => $request->input('relationship'),
            'is_primary'   => $request->boolean('is_primary'),
            'can_pickup'   => $request->boolean('can_pickup', true),
        ]);
        return $this->ok(
            data: new ParentResource($updated),
            message: 'Child linked successfully.',
        );
    }

    public function detachChild(Request $request, ParentGuardian $parent, string $studentId): JsonResponse
    {
        $this->authorize('update', $parent);
        /** @var Student $student */
        $student = Student::findOrFail($studentId);
        $updated = $this->parents->detachChild($parent, $student);
        return $this->ok(
            data: new ParentResource($updated),
            message: 'Child unlinked.',
        );
    }

    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ParentGuardian::class);
        return $this->ok(data: $this->parents->statistics(), message: 'Statistics retrieved.');
    }
}
