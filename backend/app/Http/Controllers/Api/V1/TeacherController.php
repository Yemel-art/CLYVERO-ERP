<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Models\Teacher;
use App\Services\Teacher\TeacherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TeacherController extends ApiController
{
    public function __construct(
        private readonly TeacherService $teachers,
    ) {
        $this->authorizeResource(Teacher::class, 'teacher');
    }

    public function index(Request $request): JsonResponse
    {
        $page = $this->teachers->list(
            filters:   $request->only(['q', 'status', 'gender', 'include_archived']),
            with:      ['user'],
            perPage:   (int) $request->integer('per_page', 20),
            sortBy:    (string) $request->input('sort', 'last_name'),
            sortOrder: (string) $request->input('order', 'asc'),
        );

        return $this->ok(
            data: TeacherResource::collection($page->items()),
            message: 'Teachers retrieved successfully.',
            meta: [
                'page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'last_page' => $page->lastPage(),
            ],
        );
    }

    public function show(Teacher $teacher): JsonResponse
    {
        return $this->ok(
            data: new TeacherResource($teacher->load([
                'user',
                'createdBy',
                'classesTaught.academicYear',
                'formMasterOf.academicYear',
            ])),
            message: 'Teacher retrieved successfully.',
        );
    }

    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $result = $this->teachers->register($request->toDTO(), $request->file('photo'));
        $teacher = $result['teacher'];

        return $this->created([
            'teacher' => new TeacherResource($teacher),
            'login_credentials' => $result['temporary_password'] !== null ? [
                'email' => $teacher->email,
                'temporary_password' => $result['temporary_password'],
            ] : null,
        ], 'Teacher registered successfully.');
    }

    public function update(UpdateTeacherRequest $request, Teacher $teacher): JsonResponse
    {
        return $this->ok(
            data: new TeacherResource($this->teachers->update($teacher, $request->toDTO())),
            message: 'Teacher updated successfully.',
        );
    }

    public function archive(Request $request, Teacher $teacher): JsonResponse
    {
        $this->authorize('archive', $teacher);
        return $this->ok(
            data: new TeacherResource($this->teachers->archive($teacher)),
            message: 'Teacher archived successfully.',
        );
    }

    public function restore(Request $request, string $teacherId): JsonResponse
    {
        /** @var Teacher $teacher */
        $teacher = Teacher::withTrashed()->findOrFail($teacherId);
        $this->authorize('restore', $teacher);
        return $this->ok(
            data: new TeacherResource($this->teachers->restore($teacher)),
            message: 'Teacher restored successfully.',
        );
    }

    public function uploadPhoto(Request $request, Teacher $teacher): JsonResponse
    {
        $this->authorize('update', $teacher);
        $request->validate(['photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120']]);
        return $this->ok(
            data: new TeacherResource($this->teachers->setPhoto($teacher, $request->file('photo'))),
            message: 'Photo uploaded successfully.',
        );
    }

    public function removePhoto(Request $request, Teacher $teacher): JsonResponse
    {
        $this->authorize('update', $teacher);
        return $this->ok(
            data: new TeacherResource($this->teachers->removePhoto($teacher)),
            message: 'Photo removed.',
        );
    }

    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Teacher::class);
        return $this->ok(data: $this->teachers->statistics(), message: 'Statistics retrieved.');
    }
}
