<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ParentGuardian;
use App\Repositories\Contracts\ParentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentParentRepository extends BaseRepository implements ParentRepositoryInterface
{
    protected function model(): string
    {
        return ParentGuardian::class;
    }

    public function findByEmail(string $email): ?ParentGuardian
    {
        /** @var ?ParentGuardian $p */
        $p = $this->newQuery()->where('email', strtolower($email))->first();
        return $p;
    }

    public function searchPaginated(array $filters, array $with = [], int $perPage = 20, string $sortBy = 'last_name', string $sortOrder = 'asc'): LengthAwarePaginator
    {
        $query = $this->newQuery()->with($with);
        if (! empty($filters['include_archived'])) {
            $query->withTrashed();
        }
        $this->applyFilters($query, $filters);
        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    public function countActive(): int
    {
        return $this->newQuery()->where('is_active', true)->count();
    }

    /** @param array<string, mixed> $filters */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['q'])) {
            $query->search((string) $filters['q']);
        }
        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }
        if (! empty($filters['has_children'])) {
            $query->has('students');
        }
    }
}
