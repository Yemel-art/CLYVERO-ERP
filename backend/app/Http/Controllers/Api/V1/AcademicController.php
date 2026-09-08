<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Academic\AttachSubjectRequest;
use App\Http\Requests\Academic\StoreClassRequest;
use App\Http\Requests\Academic\StoreSubjectRequest;
use App\Http\Requests\Academic\StoreTermRequest;
use App\Http\Requests\Academic\StoreYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Http\Resources\SchoolClassResource;
use App\Http\Resources\SubjectResource;
use App\Http\Resources\TermResource;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Repositories\Contracts\ClassRepositoryInterface;
use App\Repositories\Contracts\SubjectRepositoryInterface;
use App\Services\Academic\AcademicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class AcademicController extends ApiController
{
    public function __construct(
        private readonly AcademicService $academic,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly ClassRepositoryInterface $classes,
    ) {
    }

    // ─── Academic Years ─────────────────────────────────────────────

    public function years(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AcademicYear::class);
        $years = AcademicYear::orderByDesc('start_date')->get();
        return $this->ok(AcademicYearResource::collection($years), 'Academic years retrieved.');
    }

    public function storeYear(StoreYearRequest $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $year = $this->academic->createYear($request->validated() + [
            'school_id' => $schoolId,
            'status'    => AcademicYear::STATUS_UPCOMING,
        ]);
        return $this->created(new AcademicYearResource($year), 'Academic year created.');
    }

    public function activateYear(Request $request, AcademicYear $year): JsonResponse
    {
        $this->authorize('update', $year);
        return $this->ok(new AcademicYearResource($this->academic->activateYear($year)), 'Academic year activated.');
    }

    // ─── Terms ──────────────────────────────────────────────────────

    public function terms(Request $request, AcademicYear $year): JsonResponse
    {
        $this->authorize('viewAny', Term::class);
        $terms = $year->terms()->orderBy('sequence')->get();
        return $this->ok(TermResource::collection($terms), 'Terms retrieved.');
    }

    public function storeTerm(StoreTermRequest $request, AcademicYear $year): JsonResponse
    {
        $this->authorize('create', Term::class);
        $term = $this->academic->createTerm($year, $request->validated());
        return $this->created(new TermResource($term), 'Term created.');
    }

    public function activateTerm(Request $request, Term $term): JsonResponse
    {
        $this->authorize('update', $term);
        return $this->ok(new TermResource($this->academic->activateTerm($term)), 'Term activated.');
    }

    public function closeTerm(Request $request, Term $term): JsonResponse
    {
        $this->authorize('update', $term);
        return $this->ok(new TermResource($this->academic->closeTerm($term)), 'Term closed.');
    }

    // ─── Subjects ───────────────────────────────────────────────────

    public function subjects(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subject::class);
        $page = $this->subjects->searchPaginated(
            $request->only(['q', 'is_active', 'include_archived', 'education_system']),
            (int) $request->integer('per_page', 50),
        );
        return $this->ok(
            data: SubjectResource::collection($page->items()),
            message: 'Subjects retrieved.',
            meta: ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        );
    }

    public function showSubject(Subject $subject): JsonResponse
    {
        $this->authorize('view', $subject);
        return $this->ok(new SubjectResource($subject), 'Subject retrieved.');
    }

    public function storeSubject(StoreSubjectRequest $request): JsonResponse
    {
        $this->authorize('create', Subject::class);
        return $this->created(new SubjectResource($this->academic->createSubject($request->validated())), 'Subject created.');
    }

    public function updateSubject(StoreSubjectRequest $request, Subject $subject): JsonResponse
    {
        $this->authorize('update', $subject);
        return $this->ok(new SubjectResource($this->academic->updateSubject($subject, $request->validated())), 'Subject updated.');
    }

    public function archiveSubject(Request $request, Subject $subject): JsonResponse
    {
        $this->authorize('archive', $subject);
        return $this->ok(new SubjectResource($this->academic->archiveSubject($subject)), 'Subject archived.');
    }

    public function restoreSubject(Request $request, string $subjectId): JsonResponse
    {
        /** @var Subject $subject */
        $subject = Subject::withTrashed()->findOrFail($subjectId);
        $this->authorize('restore', $subject);
        return $this->ok(new SubjectResource($this->academic->restoreSubject($subject)), 'Subject restored.');
    }

    // ─── Classes ────────────────────────────────────────────────────

    public function classes(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SchoolClass::class);
        $page = $this->classes->searchPaginated(
            $request->only(['q', 'academic_year_id', 'grade_level', 'is_active', 'include_archived']),
            ['academicYear', 'formMaster'],
            (int) $request->integer('per_page', 30),
        );

        // Add students_count to each item.
        $items = new EloquentCollection($page->items());
        $items->loadCount('students');

        return $this->ok(
            data: SchoolClassResource::collection($items),
            message: 'Classes retrieved.',
            meta: ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        );
    }

    public function showClass(SchoolClass $class): JsonResponse
    {
        $this->authorize('view', $class);
        $class->load([
            'academicYear',
            'formMaster',
            'subjects',
            'students' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name'),
        ])->loadCount('students');
        return $this->ok(new SchoolClassResource($class), 'Class retrieved.');
    }

    public function storeClass(StoreClassRequest $request): JsonResponse
    {
        $this->authorize('create', SchoolClass::class);
        $class = $this->academic->createClass($request->validated());
        return $this->created(new SchoolClassResource($class->load(['academicYear', 'formMaster'])), 'Class created.');
    }

    public function updateClass(StoreClassRequest $request, SchoolClass $class): JsonResponse
    {
        $this->authorize('update', $class);
        $updated = $this->academic->updateClass($class, $request->validated());
        return $this->ok(new SchoolClassResource($updated->load(['academicYear', 'formMaster'])), 'Class updated.');
    }

    public function archiveClass(Request $request, SchoolClass $class): JsonResponse
    {
        $this->authorize('archive', $class);
        return $this->ok(new SchoolClassResource($this->academic->archiveClass($class)), 'Class archived.');
    }

    public function restoreClass(Request $request, string $classId): JsonResponse
    {
        /** @var SchoolClass $class */
        $class = SchoolClass::withTrashed()->findOrFail($classId);
        $this->authorize('restore', $class);
        return $this->ok(new SchoolClassResource($this->academic->restoreClass($class)), 'Class restored.');
    }

    // ─── Class ↔ Subject assignment ─────────────────────────────────

    public function attachSubjectToClass(AttachSubjectRequest $request, SchoolClass $class): JsonResponse
    {
        $this->authorize('update', $class);

        /** @var Subject $subject */
        $subject = Subject::findOrFail($request->input('subject_id'));
        $teacher = $request->input('teacher_id') ? Teacher::find($request->input('teacher_id')) : null;
        $weeklyFrequency = $request->integer('weekly_frequency', 3);

        $updated = $this->academic->attachSubject($class, $subject, $teacher, $weeklyFrequency);
        return $this->ok(new SchoolClassResource($updated), 'Subject attached to class.');
    }

    public function detachSubjectFromClass(Request $request, SchoolClass $class, string $subjectId): JsonResponse
    {
        $this->authorize('update', $class);
        $subject = Subject::findOrFail($subjectId);
        return $this->ok(new SchoolClassResource($this->academic->detachSubject($class, $subject)), 'Subject detached.');
    }
}
