<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Abstract Eloquent base for every repository.
 *
 * Subclasses must define `model(): string` (the Eloquent model FQCN) and
 * may override `applyFilters()` to introduce module-specific where clauses.
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * Return the fully qualified Eloquent model class.
     *
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    protected function newQuery(): Builder
    {
        $class = $this->model();

        return $class::query();
    }

    public function findById(string $id, array $with = []): ?Model
    {
        return $this->newQuery()->with($with)->find($id);
    }

    public function findByIdOrFail(string $id, array $with = []): Model
    {
        return $this->newQuery()->with($with)->findOrFail($id);
    }

    public function findOneBy(array $criteria, array $with = []): ?Model
    {
        return $this->newQuery()->with($with)->where($criteria)->first();
    }

    public function all(array $with = []): Collection
    {
        return $this->newQuery()->with($with)->get();
    }

    public function paginate(
        array $filters = [],
        array $with = [],
        int $perPage = 20,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
    ): LengthAwarePaginator {
        $query = $this->newQuery()->with($with);
        $this->applyFilters($query, $filters);
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    public function create(array $attributes): Model
    {
        $class = $this->model();
        $model = new $class($attributes);
        $model->save();

        return $model;
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes);
        $model->save();

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    public function forceDelete(Model $model): bool
    {
        return (bool) $model->forceDelete();
    }

    public function restore(Model $model): bool
    {
        return method_exists($model, 'restore') && (bool) $model->restore();
    }

    /**
     * Hook for subclasses to apply module-specific filters.
     *
     * Default impl applies equality `where` for each filter key/value.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $query->where($key, $value);
        }
    }
}
