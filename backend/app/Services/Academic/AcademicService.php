<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Services\BaseService;
use App\Services\Progression\SchoolYearTransitionService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Academic service — academic years, terms, subjects, classes.
 *
 * Business rules enforced here:
 *   - Exactly one active academic year at a time.
 *   - Exactly one active term per academic year.
 *   - Subjects are reusable across years; only the pivot resets each year.
 *   - When a class is archived, students keep their class_id reference
 *     so transcripts remain consistent.
 */
class AcademicService extends BaseService
{
    public function __construct(
        private readonly SchoolYearTransitionService $transitions,
        private readonly TenantContext $tenant,
        private readonly DefaultAcademicTerms $defaultTerms,
    ) {}

    // ─── Academic Years ─────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createYear(array $attributes): AcademicYear
    {
        return $this->transaction(function () use ($attributes): AcademicYear {
            $schoolId = $attributes['school_id'] ?? $this->tenant->schoolId();
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["academic-year-create:{$schoolId}"]);
            $attributes['school_id'] = $schoolId;

            $year = AcademicYear::create($attributes + [
                'status' => $attributes['status'] ?? AcademicYear::STATUS_UPCOMING,
            ]);
            $this->defaultTerms->provision($year);

            return $year;
        });
    }

    /**
     * Activate one academic year and close every other one. Only the
     * active year accepts new enrollment.
     */
    public function activateYear(AcademicYear $year): AcademicYear
    {
        return $this->transaction(function () use ($year): AcademicYear {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["academic-year-activate:{$year->school_id}"]);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($year->id);
            if ($year->status !== AcademicYear::STATUS_UPCOMING) {
                throw ValidationException::withMessages(['academic_year' => __('progression.errors.year_not_upcoming')]);
            }

            $current = AcademicYear::query()
                ->where('school_id', $year->school_id)
                ->where('status', AcademicYear::STATUS_ACTIVE)
                ->where('id', '!=', $year->id)
                ->lockForUpdate()
                ->first();
            if ($current !== null && $year->start_date->lessThanOrEqualTo($current->start_date)) {
                throw ValidationException::withMessages(['academic_year' => __('progression.errors.year_not_after_current')]);
            }

            if ($current !== null) {
                $this->transitions->rollover($current, $year);
            } else {
                $year->update(['status' => AcademicYear::STATUS_ACTIVE]);
                $year->school()->update(['current_academic_year_id' => $year->id]);
            }

            $this->defaultTerms->provision($year);

            return $year->fresh() ?? $year;
        });
    }

    // ─── Terms ──────────────────────────────────────────────────────

    /**
     * @param  array{name:string, sequence:int, start_date:string, end_date:string, status?:string}  $attributes
     */
    public function createTerm(AcademicYear $year, array $attributes): Term
    {
        return $this->transaction(function () use ($year, $attributes): Term {
            return Term::create($attributes + [
                'academic_year_id' => $year->id,
                'status' => $attributes['status'] ?? Term::STATUS_UPCOMING,
            ]);
        });
    }

    public function activateTerm(Term $term): Term
    {
        return $this->transaction(function () use ($term): Term {
            // Close every other term in this academic year.
            Term::where('academic_year_id', $term->academic_year_id)
                ->where('id', '!=', $term->id)
                ->where('status', Term::STATUS_ACTIVE)
                ->update(['status' => Term::STATUS_CLOSED]);
            $term->update(['status' => Term::STATUS_ACTIVE]);

            return $term->fresh() ?? $term;
        });
    }

    public function closeTerm(Term $term): Term
    {
        $term->update(['status' => Term::STATUS_CLOSED]);

        return $term->fresh() ?? $term;
    }

    // ─── Subjects ───────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createSubject(array $attributes): Subject
    {
        return $this->transaction(
            fn () => Subject::create($attributes + ['created_by' => Auth::id()])
        );
    }

    public function updateSubject(Subject $subject, array $attributes): Subject
    {
        return $this->transaction(function () use ($subject, $attributes): Subject {
            $subject->update($attributes);
            if (array_key_exists('coefficient', $attributes)) {
                DB::table('class_subject')
                    ->where('subject_id', $subject->id)
                    ->update(['coefficient' => (float) $attributes['coefficient'], 'updated_at' => now()]);
            }

            return $subject->fresh() ?? $subject;
        });
    }

    public function archiveSubject(Subject $subject): Subject
    {
        $this->transaction(function () use ($subject): void {
            $subject->update(['is_active' => false]);
            $subject->delete();
        });

        return $subject;
    }

    public function restoreSubject(Subject $subject): Subject
    {
        $this->transaction(function () use ($subject): void {
            $subject->restore();
            if (Subject::query()->where('id', '!=', $subject->id)->where('color', $subject->color)->exists()) {
                $subject->color = collect(Subject::COLOR_PALETTE)->first(
                    fn (string $color): bool => ! Subject::query()->where('color', $color)->exists(),
                ) ?? $subject->color;
            }
            $subject->update(['is_active' => true]);
        });

        return $subject;
    }

    // ─── Classes ────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createClass(array $attributes): SchoolClass
    {
        return $this->transaction(function () use ($attributes): SchoolClass {
            $attributes['next_grade_level'] ??= SchoolClass::nextLevelFor($attributes['grade_level'] ?? null);
            $attributes['is_terminal'] ??= SchoolClass::isTerminalLevel($attributes['grade_level'] ?? null);

            return SchoolClass::create($attributes + ['created_by' => Auth::id()]);
        });
    }

    public function updateClass(SchoolClass $class, array $attributes): SchoolClass
    {
        if (array_key_exists('grade_level', $attributes)) {
            $attributes['next_grade_level'] ??= SchoolClass::nextLevelFor($attributes['grade_level']);
            $attributes['is_terminal'] ??= SchoolClass::isTerminalLevel($attributes['grade_level']);
        }
        $class->update($attributes);

        return $class->fresh() ?? $class;
    }

    public function archiveClass(SchoolClass $class): SchoolClass
    {
        $this->transaction(function () use ($class): void {
            $class->update(['is_active' => false]);
            $class->delete();
        });

        return $class;
    }

    public function restoreClass(SchoolClass $class): SchoolClass
    {
        $this->transaction(function () use ($class): void {
            $class->restore();
            $class->update(['is_active' => true]);
        });

        return $class;
    }

    /**
     * Attach a subject (taught by a specific teacher) to a class.
     */
    public function attachSubject(
        SchoolClass $class,
        Subject $subject,
        ?Teacher $teacher = null,
        int $weeklyFrequency = 3,
    ): SchoolClass {
        if ($subject->education_system !== 'both' && $subject->education_system !== $class->education_system) {
            throw ValidationException::withMessages([
                'subject_id' => ['The subject is not configured for this class education system.'],
            ]);
        }
        $existing = DB::table('class_subject')
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->exists();

        $pivot = [
            'teacher_id' => $teacher?->id,
            'coefficient' => (float) $subject->coefficient,
            'weekly_frequency' => $weeklyFrequency,
            'updated_at' => now(),
        ];

        if ($existing) {
            $class->subjects()->updateExistingPivot($subject->id, $pivot);
        } else {
            $class->subjects()->attach($subject->id, $pivot + [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);
        }

        return $class->load('subjects');
    }

    public function detachSubject(SchoolClass $class, Subject $subject): SchoolClass
    {
        $class->subjects()->detach($subject->id);

        return $class->load('subjects');
    }

    /**
     * Update the teacher assigned to a subject in a class.
     */
    public function assignSubjectTeacher(SchoolClass $class, Subject $subject, ?Teacher $teacher): SchoolClass
    {
        $class->subjects()->updateExistingPivot($subject->id, [
            'teacher_id' => $teacher?->id,
        ]);

        return $class->load('subjects');
    }

    public function enrollStudentsCount(SchoolClass $class): int
    {
        return $class->students()->count();
    }
}
