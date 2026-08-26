<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\TeacherStatus;
use App\Models\Teacher;
use App\Repositories\Contracts\TeacherRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;

class EloquentTeacherRepository extends BaseRepository implements TeacherRepositoryInterface
{
    protected function model(): string
    {
        return Teacher::class;
    }

    public function findByEmployeeNumber(string $n): ?Teacher
    {
        /** @var ?Teacher $t */
        $t = $this->newQuery()->where('employee_number', $n)->first();
        return $t;
    }

    /**
     * Format: TL-T-YYYY-NNNN (T = teacher).
     */
    public function nextEmployeeNumber(int $year): string
    {
        $prefix = sprintf('%s-T-%d-', app(TenantContext::class)->schoolCode(), $year);
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["teacher-number:{$prefix}"]);
        $last = Teacher::withTrashed()
            ->where('employee_number', 'like', $prefix . '%')
            ->orderByDesc('employee_number')
            ->value('employee_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return sprintf('%s%04d', $prefix, $next);
    }

    public function searchPaginated(array $filters, array $with = [], int $perPage = 20, string $sortBy = 'last_name', string $sortOrder = 'asc'): LengthAwarePaginator
    {
        $query = $this->newQuery()->with($with);
        if (! empty($filters['include_archived']) || ($filters['status'] ?? null) === TeacherStatus::Archived->value) {
            $query->withTrashed();
        }
        $this->applyFilters($query, $filters);
        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    public function countByStatus(string $status): int
    {
        $query = $this->newQuery();
        if ($status === TeacherStatus::Archived->value) {
            $query->withTrashed();
        }
        return $query->where('status', $status)->count();
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['q'])) {
            $query->search((string) $filters['q']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } elseif (empty($filters['include_archived'])) {
            $query->where('status', '!=', TeacherStatus::Archived->value);
        }
        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
    }
}
