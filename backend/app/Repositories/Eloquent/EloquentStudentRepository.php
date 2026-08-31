<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;

class EloquentStudentRepository extends BaseRepository implements StudentRepositoryInterface
{
    protected function model(): string
    {
        return Student::class;
    }

    public function findByAdmissionNumber(string $admissionNumber): ?Student
    {
        /** @var ?Student $student */
        $student = $this->newQuery()->where('admission_number', $admissionNumber)->first();
        return $student;
    }

    /**
     * Generate the next admission number for an academic year.
     *
     * Format: TL-YYYY-NNNN (e.g. TL-2026-0001) where YYYY is the start
     * year of the academic year and NNNN is a zero-padded sequence.
     *
     * Source: Business Workflow §3 (Student Registration) — institution
     * prefix + year + sequence.
     */
    public function nextAdmissionNumber(int $academicYear): string
    {
        $prefix = sprintf('%s-%d-', app(TenantContext::class)->schoolCode(), $academicYear);
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["student-number:{$prefix}"]);

        $lastNumber = Student::withTrashed()
            ->where('admission_number', 'like', $prefix . '%')
            ->orderByDesc('admission_number')
            ->value('admission_number');

        $nextSequence = $lastNumber
            ? ((int) substr($lastNumber, strlen($prefix))) + 1
            : 1;

        return sprintf('%s%04d', $prefix, $nextSequence);
    }

    public function searchPaginated(
        array $filters,
        array $with = [],
        int $perPage = 20,
        string $sortBy = 'last_name',
        string $sortOrder = 'asc',
    ): LengthAwarePaginator {
        $query = $this->newQuery()->with($with);
        if (! empty($filters['include_archived']) || ($filters['status'] ?? null) === StudentStatus::Archived->value) {
            $query->withTrashed();
        }
        $this->applyFilters($query, $filters);
        $query->orderBy($sortBy, $sortOrder);
        return $query->paginate($perPage);
    }

    public function countByStatus(string $status): int
    {
        $query = $this->newQuery();
        if ($status === StudentStatus::Archived->value) {
            $query->withTrashed();
        }
        return $query->where('status', $status)->count();
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        // Search term against name, admission number, email.
        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->search($term);
            $prefix = str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $query->orderByRaw(
                'CASE WHEN first_name ILIKE ? OR last_name ILIKE ? OR admission_number ILIKE ? OR official_matricule ILIKE ? THEN 0 ELSE 1 END',
                [$prefix, $prefix, $prefix, $prefix],
            );
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } elseif (empty($filters['include_archived'])) {
            // Default scope excludes archived unless explicitly requested.
            $query->where('status', '!=', StudentStatus::Archived->value);
        }

        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (! empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }
    }
}
