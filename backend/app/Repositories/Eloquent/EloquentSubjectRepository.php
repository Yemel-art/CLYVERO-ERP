<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Subject;
use App\Repositories\Contracts\SubjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentSubjectRepository extends BaseRepository implements SubjectRepositoryInterface
{
    protected function model(): string { return Subject::class; }

    public function searchPaginated(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $q = $this->newQuery();
        if (! empty($filters['include_archived'])) {
            $q->withTrashed();
        }
        if (! empty($filters['q'])) $q->search((string) $filters['q']);
        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $q->where('is_active', (bool) $filters['is_active']);
        }
        if (! empty($filters['education_system'])) {
            $q->whereIn('education_system', ['both', (string) $filters['education_system']]);
        }
        return $q->orderBy('name')->paginate($perPage);
    }
}
