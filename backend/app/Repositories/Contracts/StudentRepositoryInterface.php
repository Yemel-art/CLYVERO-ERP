<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentRepositoryInterface extends RepositoryInterface
{
    public function findByAdmissionNumber(string $admissionNumber): ?Student;

    public function nextAdmissionNumber(int $academicYear): string;

    /**
     * @param  array{
     *   q?: string|null,
     *   status?: string|null,
     *   gender?: string|null,
     *   class_id?: string|null,
     *   academic_year_id?: string|null,
     *   include_archived?: bool,
     * }  $filters
     * @param  array<int, string>  $with
     */
    public function searchPaginated(
        array $filters,
        array $with = [],
        int $perPage = 20,
        string $sortBy = 'last_name',
        string $sortOrder = 'asc',
    ): LengthAwarePaginator;

    public function countByStatus(string $status): int;
}
