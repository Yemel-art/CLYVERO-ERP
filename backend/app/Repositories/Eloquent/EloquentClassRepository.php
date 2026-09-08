<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Repositories\Contracts\ClassRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class EloquentClassRepository extends BaseRepository implements ClassRepositoryInterface
{
    protected function model(): string { return SchoolClass::class; }

    public function searchPaginated(array $filters, array $with = [], int $perPage = 20): LengthAwarePaginator
    {
        $q = $this->newQuery()->with($with);
        $user = Auth::user();
        if ($user?->isTeacher()) {
            $teacherId = Teacher::resolveForUser($user)?->id;
            $q->where(function ($assigned) use ($teacherId): void {
                if ($teacherId === null) {
                    $assigned->whereRaw('1 = 0');
                    return;
                }
                $assigned->where('form_master_id', $teacherId)
                    ->orWhereHas('subjects', fn ($subject) => $subject->where('class_subject.teacher_id', $teacherId));
            });
        }
        if (! empty($filters['include_archived'])) $q->withTrashed();
        if (! empty($filters['q'])) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%';
            $q->where(fn ($match) => $match->where('name', 'ilike', $like)->orWhere('grade_level', 'ilike', $like));
        }
        if (! empty($filters['academic_year_id'])) $q->where('academic_year_id', $filters['academic_year_id']);
        if (! empty($filters['grade_level'])) $q->where('grade_level', $filters['grade_level']);
        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $q->where('is_active', (bool) $filters['is_active']);
        }

        return $q->orderBy('grade_level')->orderBy('name')->paginate(min(max($perPage, 1), 100));
    }
}
