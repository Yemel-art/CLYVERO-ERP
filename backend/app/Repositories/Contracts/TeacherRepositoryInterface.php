<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TeacherRepositoryInterface extends RepositoryInterface
{
    public function findByEmployeeNumber(string $n): ?Teacher;

    public function nextEmployeeNumber(int $year): string;

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>    $with
     */
    public function searchPaginated(array $filters, array $with = [], int $perPage = 20, string $sortBy = 'last_name', string $sortOrder = 'asc'): LengthAwarePaginator;

    public function countByStatus(string $status): int;
}
