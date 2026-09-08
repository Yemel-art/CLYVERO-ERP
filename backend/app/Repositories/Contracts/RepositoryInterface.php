<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The contract every repository fulfils.
 *
 * Services consume this interface, not the concrete Eloquent class —
 * keeps Services testable and decoupled from the ORM.
 */
interface RepositoryInterface
{
    /**
     * @param  array<int, string>  $with
     */
    public function findById(string $id, array $with = []): ?Model;

    /**
     * @param  array<int, string>  $with
     */
    public function findByIdOrFail(string $id, array $with = []): Model;

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<int, string>    $with
     */
    public function findOneBy(array $criteria, array $with = []): ?Model;

    /**
     * @param  array<int, string>  $with
     */
    public function all(array $with = []): Collection;

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>    $with
     */
    public function paginate(
        array $filters = [],
        array $with = [],
        int $perPage = 20,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
    ): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;

    public function forceDelete(Model $model): bool;

    public function restore(Model $model): bool;
}
