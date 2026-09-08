<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTO\ParentGuardian\ParentGuardianDTO;
use App\Enums\Gender;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\PermanentlyDeleteStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Requests\Student\UploadStudentPhotoRequest;
use App\Http\Resources\StudentEnrollmentResource;
use App\Http\Resources\StudentResource;
use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Services\Finance\FinanceService;
use App\Services\ParentGuardian\ParentService;
use App\Services\Student\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Student CRUD + archive/restore + photo upload + statistics.
 *
 * Routes are registered in routes/api.php under /api/v1/students.
 * Authorization is delegated to StudentPolicy via the
 * authorizeResource() binding.
 *
 * Source: API Blueprint §8 Student Endpoints + SRS Student Management.
 */
final class StudentController extends ApiController
{
    public function __construct(
        private readonly StudentService $students,
        private readonly FinanceService $finance,
        private readonly ParentService $parents,
    ) {
        $this->authorizeResource(Student::class, 'student');
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'q', 'status', 'gender', 'class_id', 'academic_year_id', 'include_archived',
        ]);

        $page = $this->students->list(
            filters: $filters,
            with: ['academicYear', 'schoolClass'],
            perPage: (int) $request->integer('per_page', 20),
            sortBy: (string) $request->input('sort', 'last_name'),
            sortOrder: (string) $request->input('order', 'asc'),
        );

        return $this->ok(
            data: StudentResource::collection($page->items()),
            message: 'Students retrieved successfully.',
            meta: [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        );
    }

    public function show(Student $student): JsonResponse
    {
        $student->load(['academicYear', 'createdBy', 'primaryParent.user', 'schoolClass']);

        return $this->ok(
            data: new StudentResource($student),
            message: 'Student retrieved successfully.',
        );
    }

    public function academicHistory(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $enrollments = $student->enrollments()
            ->with(['academicYear', 'schoolClass', 'academicDecision'])
            ->get();

        return $this->ok(
            data: StudentEnrollmentResource::collection($enrollments),
            message: 'Academic history retrieved successfully.',
        );
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = DB::transaction(function () use ($request): Student {
            $student = $this->students->register(
                dto: $request->toDTO(),
                photo: $request->file('photo'),
            );

            $parent = null;
            $temporaryPassword = null;
            if ($request->string('guardian_mode')->toString() === 'existing') {
                $parent = ParentGuardian::findOrFail($request->string('existing_parent_id')->toString());
                $this->parents->attachChild($parent, $student, [
                    'relationship' => $request->string('parent_relationship')->toString(),
                    'is_primary' => true,
                    'can_pickup' => true,
                ]);
            } else {
                $parent = $this->parents->register(new ParentGuardianDTO(
                    firstName: $request->string('parent_first_name')->toString(),
                    lastName: $request->string('parent_last_name')->toString(),
                    gender: Gender::from($request->string('parent_gender')->toString()),
                    email: strtolower($request->string('parent_email')->toString()),
                    phone: $request->string('parent_phone')->toString(),
                    country: 'Cameroon',
                    createUserAccount: $request->boolean('create_parent_account', true),
                    children: [[
                        'student_id' => $student->id,
                        'relationship' => $request->string('parent_relationship')->toString(),
                        'is_primary' => true,
                        'can_pickup' => true,
                    ]],
                ));
                $temporaryPassword = $parent->getAttribute('temporary_password');
            }

            $year = AcademicYear::findOrFail($request->string('academic_year_id')->toString());
            $invoice = $this->finance->generateInvoiceForStudent($student, $year);
            $initialPayment = (float) $request->input('initial_payment', 0);
            if ($initialPayment > 0) {
                $this->finance->recordPayment($invoice, [
                    'paid_at' => $request->string('enrollment_date')->toString(),
                    'amount' => $initialPayment,
                    'method' => 'cash',
                    'notes' => 'Physical payment recorded during student registration.',
                ]);
            }

            $student->refresh()->load(['academicYear', 'createdBy', 'primaryParent.user', 'schoolClass']);
            if ($temporaryPassword !== null && $parent !== null) {
                $student->setAttribute('parent_credentials', [
                    'email' => $parent->email,
                    'temporary_password' => $temporaryPassword,
                ]);
            }

            return $student;
        });

        return $this->created(
            data: new StudentResource($student),
            message: 'Student registered successfully.',
        );
    }

    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $updated = DB::transaction(function () use ($request, $student): Student {
            $updated = $this->students->update($student, $request->toDTO());

            // Registration already creates the initial invoice.  Only try to
            // backfill one during an edit when the academic placement itself
            // changed; a simple name, phone, or photo correction must never
            // be blocked by missing fee configuration.
            if ($request->hasAny(['academic_year_id', 'class_id'])
                && $updated->academic_year_id
                && ! Invoice::query()
                    ->where('student_id', $updated->id)
                    ->where('academic_year_id', $updated->academic_year_id)
                    ->where('status', '!=', Invoice::STATUS_CANCELLED)
                    ->exists()) {
                $year = AcademicYear::findOrFail($updated->academic_year_id);
                $this->finance->generateInvoiceForStudent($updated, $year);
            }

            return $updated;
        });
        $updated->load(['academicYear', 'createdBy', 'primaryParent.user', 'schoolClass']);

        return $this->ok(
            data: new StudentResource($updated),
            message: 'Student updated successfully.',
        );
    }

    public function archive(Request $request, Student $student): JsonResponse
    {
        $this->authorize('archive', $student);

        $archived = $this->students->archive($student);

        return $this->ok(
            data: new StudentResource($archived),
            message: 'Student archived successfully.',
        );
    }

    public function restore(Request $request, string $studentId): JsonResponse
    {
        /** @var Student $student */
        $student = Student::withTrashed()->findOrFail($studentId);
        $this->authorize('restore', $student);

        $restored = $this->students->restore($student);

        return $this->ok(
            data: new StudentResource($restored),
            message: 'Student restored successfully.',
        );
    }

    public function permanentlyDelete(PermanentlyDeleteStudentRequest $request, Student $student): JsonResponse
    {
        $this->students->permanentlyDelete($student, $request->string('reason')->toString());

        return $this->ok(null, 'The test student record was permanently deleted.');
    }

    public function uploadPhoto(UploadStudentPhotoRequest $request, Student $student): JsonResponse
    {
        $updated = $this->students->setPhoto($student, $request->file('photo'));

        return $this->ok(
            data: new StudentResource($updated),
            message: 'Photo uploaded successfully.',
        );
    }

    public function removePhoto(Request $request, Student $student): JsonResponse
    {
        $this->authorize('update', $student);
        $updated = $this->students->removePhoto($student);

        return $this->ok(
            data: new StudentResource($updated),
            message: 'Photo removed.',
        );
    }

    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Student::class);

        return $this->ok(
            data: $this->students->statistics(),
            message: 'Statistics retrieved.',
        );
    }
}
