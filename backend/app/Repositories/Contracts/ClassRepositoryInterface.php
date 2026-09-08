<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClassRepositoryInterface extends RepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     * @param array<int, string>   $with
     */
    public function searchPaginated(array $filters, array $with = [], int $perPage = 20): LengthAwarePaginator;
}
