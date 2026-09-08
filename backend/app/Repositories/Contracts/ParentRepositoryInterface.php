<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ParentGuardian;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ParentRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?ParentGuardian;

    /**
     * @param array<string, mixed> $filters
     * @param array<int, string>   $with
     */
    public function searchPaginated(array $filters, array $with = [], int $perPage = 20, string $sortBy = 'last_name', string $sortOrder = 'asc'): LengthAwarePaginator;

    public function countActive(): int;
}
