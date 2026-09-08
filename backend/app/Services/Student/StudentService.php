<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\DTO\Student\StudentDTO;
use App\Enums\StudentStatus;
use App\Exceptions\PhotoUploadException;
use App\Models\AcademicYear;
use App\Models\AcademicDecision;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Repositories\Contracts\StudentRepositoryInterface;
use App\Repositories\Contracts\ProgressionRepositoryInterface;
use App\Services\TenantContext;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * Student business logic.
 *
 * Responsibilities (per Workflow §3 Student Registration + SRS Student
 * Management module):
 *   - Store the school's manually assigned student matriculation number.
 *   - Default the academic year to the school's current one when caller
 *     omits it.
 *   - Process and store profile photos (square crop, 400x400 JPEG).
 *   - Archive (soft delete) / restore students without losing records.
 *   - Provide paginated, filterable search.
 */
class StudentService extends BaseService
{
    private const PHOTO_DISK = 'public';
    private const PHOTO_DIR  = 'students';
    private const PHOTO_SIZE = 400; // square

    public function __construct(
        private readonly StudentRepositoryInterface $students,
        private readonly ProgressionRepositoryInterface $progression,
        private readonly TenantContext $tenant,
    ) {
    }

    /**
     * Register a new student with the validated school-issued matriculation
     * number, assign the active academic year when omitted, and optionally
     * process a profile photo upload.
     */
    public function register(StudentDTO $dto, ?UploadedFile $photo = null): Student
    {
        return $this->transaction(function () use ($dto, $photo): Student {
            $academicYearId = $dto->academicYearId ?? $this->resolveActiveAcademicYearId();
            $academicYear   = $academicYearId
                ? AcademicYear::find($academicYearId)
                : null;

            $attributes = $dto->toAttributes() + [
                'school_id' => $this->tenant->schoolId() ?? $academicYear?->school_id,
                'academic_year_id' => $academicYearId,
                'status'           => StudentStatus::Active->value,
                'created_by'       => Auth::id(),
            ];
            $attributes['official_matricule'] = $attributes['admission_number'] ?? null;

            /** @var Student $student */
            $student = $this->students->create($attributes);
            $this->progression->syncEnrollment($student);

            if ($photo !== null) {
                $this->setPhoto($student, $photo);
            }

            return $student->load(['academicYear', 'createdBy']);
        });
    }

    public function update(Student $student, StudentDTO $dto): Student
    {
        return $this->transaction(
            function () use ($student, $dto): Student {
                $attributes = $dto->toAttributes();
                if (isset($attributes['admission_number'])) {
                    $attributes['official_matricule'] = $attributes['admission_number'];
                }
                $updated = $this->students->update($student, $attributes);
                $this->progression->syncEnrollment($updated);

                return $updated->load(['academicYear', 'createdBy']);
            }
        );
    }

    /**
     * Archive a student (soft delete + status = archived). Reversible
     * via `restore()`. Preserves all academic and financial records.
     */
    public function archive(Student $student): Student
    {
        return $this->transaction(function () use ($student): Student {
            $student->status = StudentStatus::Archived;
            $student->save();
            $student->delete(); // soft delete
            return $student->fresh() ?? $student;
        });
    }

    public function restore(Student $student): Student
    {
        return $this->transaction(function () use ($student): Student {
            $student->restore();
            $student->status = StudentStatus::Active;
            $student->save();
            return $student->fresh() ?? $student;
        });
    }

    public function permanentlyDelete(Student $student, string $reason): void
    {
        $this->transaction(function () use ($student, $reason): void {
            $student = Student::withTrashed()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            if (Payment::query()->where('student_id', $student->id)->exists()) {
                throw ValidationException::withMessages([
                    'student' => ['This student has financial payments and cannot be permanently deleted. Archive the record instead.'],
                ]);
            }

            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'module' => 'student',
                'action' => 'permanently_deleted',
                'auditable_type' => Student::class,
                'auditable_id' => $student->id,
                'old_values' => [
                    'admission_number' => $student->admission_number,
                    'full_name' => $student->full_name,
                    'reason' => $reason,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            AcademicDecision::query()->where('student_id', $student->id)->delete();
            StudentEnrollment::query()->where('student_id', $student->id)->delete();
            if ($student->photo && Storage::disk(self::PHOTO_DISK)->exists($student->photo)) {
                Storage::disk(self::PHOTO_DISK)->delete($student->photo);
            }
            $student->forceDelete();
        });
    }

    public function setPhoto(Student $student, UploadedFile $photo): Student
    {
        // Reprocess to a normalized JPEG, 400x400, oriented correctly.
        try {
            $processed = Image::read($photo->getRealPath())
                ->cover(self::PHOTO_SIZE, self::PHOTO_SIZE)
                ->toJpeg(85);
        } catch (Throwable $e) {
            throw new PhotoUploadException('Image could not be processed (' . $e->getMessage() . ').');
        }

        $relativePath = self::PHOTO_DIR . '/' . $student->id . '-' . Str::random(8) . '.jpg';
        if (! Storage::disk(self::PHOTO_DISK)->put($relativePath, (string) $processed)) {
            throw new PhotoUploadException('The processed image could not be written to public storage.');
        }

        // Remove the previous photo, if any.
        if ($student->photo && Storage::disk(self::PHOTO_DISK)->exists($student->photo)) {
            Storage::disk(self::PHOTO_DISK)->delete($student->photo);
        }

        $student->photo = $relativePath;
        $student->save();

        return $student->refresh();
    }

    public function removePhoto(Student $student): Student
    {
        if ($student->photo && Storage::disk(self::PHOTO_DISK)->exists($student->photo)) {
            Storage::disk(self::PHOTO_DISK)->delete($student->photo);
        }
        $student->photo = null;
        $student->save();
        return $student->refresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>    $with
     */
    public function list(
        array $filters,
        array $with = [],
        int $perPage = 20,
        string $sortBy = 'last_name',
        string $sortOrder = 'asc',
    ): LengthAwarePaginator {
        return $this->students->searchPaginated($filters, $with, $perPage, $sortBy, $sortOrder);
    }

    /**
     * @return array<string, int>
     */
    public function statistics(): array
    {
        return [
            'total'      => $this->students->countByStatus(StudentStatus::Active->value),
            'archived'   => $this->students->countByStatus(StudentStatus::Archived->value),
            'graduated'  => $this->students->countByStatus(StudentStatus::Graduated->value),
            'withdrawn'  => $this->students->countByStatus(StudentStatus::Withdrawn->value),
        ];
    }

    private function resolveActiveAcademicYearId(): ?string
    {
        return AcademicYear::active()->value('id');
    }
}
