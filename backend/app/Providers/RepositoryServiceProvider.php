<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\StudentRepositoryInterface;
use App\Repositories\Contracts\ProgressionRepositoryInterface;
use App\Repositories\Contracts\TeacherRepositoryInterface;
use App\Repositories\Contracts\ParentRepositoryInterface;
use App\Repositories\Contracts\AcademicYearRepositoryInterface;
use App\Repositories\Contracts\TermRepositoryInterface;
use App\Repositories\Contracts\SubjectRepositoryInterface;
use App\Repositories\Contracts\ClassRepositoryInterface;
use App\Repositories\Eloquent\EloquentAcademicYearRepository;
use App\Repositories\Eloquent\EloquentTermRepository;
use App\Repositories\Eloquent\EloquentSubjectRepository;
use App\Repositories\Eloquent\EloquentClassRepository;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentStudentRepository;
use App\Repositories\Eloquent\EloquentProgressionRepository;
use App\Repositories\Eloquent\EloquentTeacherRepository;
use App\Repositories\Eloquent\EloquentParentRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Repository ↔ Interface bindings.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepositoryInterface::class    => EloquentUserRepository::class,
        ProgressionRepositoryInterface::class => EloquentProgressionRepository::class,
        StudentRepositoryInterface::class => EloquentStudentRepository::class,
        TeacherRepositoryInterface::class => EloquentTeacherRepository::class,
        ParentRepositoryInterface::class  => EloquentParentRepository::class,
        AcademicYearRepositoryInterface::class => EloquentAcademicYearRepository::class,
        TermRepositoryInterface::class         => EloquentTermRepository::class,
        SubjectRepositoryInterface::class      => EloquentSubjectRepository::class,
        ClassRepositoryInterface::class        => EloquentClassRepository::class,

        // Phase 3 — TeacherRepositoryInterface::class => EloquentTeacherRepository::class,
        ParentRepositoryInterface::class  => EloquentParentRepository::class,
        AcademicYearRepositoryInterface::class => EloquentAcademicYearRepository::class,
        TermRepositoryInterface::class         => EloquentTermRepository::class,
        SubjectRepositoryInterface::class      => EloquentSubjectRepository::class,
        ClassRepositoryInterface::class        => EloquentClassRepository::class,
        // Phase 4 — ParentRepositoryInterface::class  => EloquentParentRepository::class,
        AcademicYearRepositoryInterface::class => EloquentAcademicYearRepository::class,
        TermRepositoryInterface::class         => EloquentTermRepository::class,
        SubjectRepositoryInterface::class      => EloquentSubjectRepository::class,
        ClassRepositoryInterface::class        => EloquentClassRepository::class,
        // Phase 5 — ClassRepositoryInterface::class   => EloquentClassRepository::class,
        // etc.
    ];

    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
