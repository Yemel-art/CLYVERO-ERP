<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SubjectRepositoryInterface extends RepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function searchPaginated(array $filters, int $perPage = 20): LengthAwarePaginator;
}
