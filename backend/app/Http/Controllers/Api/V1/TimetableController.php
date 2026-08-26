<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Timetable\StoreSlotRequest;
use App\Http\Requests\Timetable\GenerateTimetableRequest;
use App\Http\Requests\Timetable\SaveGenerationConfigRequest;
use App\Http\Resources\TimetableSlotResource;
use App\Models\SchoolClass;
use App\Models\TimetableSlot;
use App\Services\Timetable\TimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TimetableController extends ApiController
{
    public function __construct(
        private readonly TimetableService $timetable,
    ) {
    }

    public function classSlots(Request $request, SchoolClass $class): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('timetable.view'), 403);
        return $this->ok(
            TimetableSlotResource::collection($this->timetable->forClass($class)),
            'Class timetable retrieved.',
        );
    }

    public function teacherSlots(Request $request, string $teacherId): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('timetable.view'), 403);
        return $this->ok(
            TimetableSlotResource::collection($this->timetable->forTeacher($teacherId)),
            'Teacher timetable retrieved.',
        );
    }

    public function mySlots(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('timetable.view'), 403);

        return $this->ok(
            TimetableSlotResource::collection($this->timetable->forTeacherUser((string) $request->user()->id)),
            'Teacher timetable retrieved.',
        );
    }

    public function schoolSlots(Request $request, string $academicYearId): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('timetable.view'), 403);

        return $this->ok(
            TimetableSlotResource::collection($this->timetable->forSchoolYear($academicYearId)),
            'School timetable retrieved.',
        );
    }

    public function generationConfig(Request $request, string $academicYearId): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('timetable.view'), 403);

        return $this->ok(
            $this->timetable->generationConfig($academicYearId),
            'Timetable generation configuration retrieved.',
        );
    }

    public function saveGenerationConfig(SaveGenerationConfigRequest $request): JsonResponse
    {
        $config = $this->timetable->saveGenerationConfig($request->validated());

        return $this->ok([
            'academic_year_id' => $config->academic_year_id,
            'working_days' => $config->working_days,
            'periods' => $config->periods,
            'break_periods' => $config->break_periods,
        ], 'Timetable generation configuration saved.');
    }

    public function generate(GenerateTimetableRequest $request): JsonResponse
    {
        $result = $this->timetable->generate(
            $request->string('academic_year_id')->toString(),
            $request->boolean('replace_existing'),
        );

        return $this->ok(
            $result,
            $result['generated'] ? 'Timetable generated.' : 'Timetable could not be generated.',
        );
    }

    public function store(StoreSlotRequest $request): JsonResponse
    {
        $slot = $this->timetable->createSlot($request->validated());
        return $this->created(
            new TimetableSlotResource($slot->load(['subject', 'teacher'])),
            'Slot created.',
        );
    }

    public function update(StoreSlotRequest $request, TimetableSlot $slot): JsonResponse
    {
        $updated = $this->timetable->updateSlot($slot, $request->validated());
        return $this->ok(
            new TimetableSlotResource($updated->load(['subject', 'teacher'])),
            'Slot updated.',
        );
    }

    public function destroy(Request $request, TimetableSlot $slot): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('timetable.edit'), 403);
        $this->timetable->deleteSlot($slot);
        return $this->ok(null, 'Slot deleted.');
    }
}
