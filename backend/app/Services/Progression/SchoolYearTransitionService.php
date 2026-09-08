<?php

declare(strict_types=1);

namespace App\Services\Progression;

use App\Enums\AcademicDecisionStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolYearTransition;
use App\Repositories\Contracts\ProgressionRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SchoolYearTransitionService extends BaseService
{
    public function __construct(
        private readonly ProgressionRepositoryInterface $progression,
        private readonly PromotionEngine $promotionEngine,
    ) {}

    public function rollover(AcademicYear $fromYear, AcademicYear $toYear): SchoolYearTransition
    {
        if ($fromYear->school_id !== $toYear->school_id) {
            throw ValidationException::withMessages(['academic_year' => __('progression.errors.cross_school_year')]);
        }
        if ($fromYear->status !== AcademicYear::STATUS_ACTIVE || $toYear->status !== AcademicYear::STATUS_UPCOMING) {
            throw ValidationException::withMessages(['academic_year' => __('progression.errors.invalid_transition_state')]);
        }
        if ($toYear->start_date->lessThanOrEqualTo($fromYear->start_date)) {
            throw ValidationException::withMessages(['academic_year' => __('progression.errors.year_not_after_current')]);
        }

        return $this->transaction(function () use ($fromYear, $toYear): SchoolYearTransition {
            // Serialize rollover attempts for the same school. This prevents
            // duplicate enrollments when two administrators click at once.
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["school-year-rollover:{$fromYear->school_id}"]);
            $transition = $this->progression->startTransition($fromYear, $toYear, Auth::id());
            if ($transition->status === 'completed') {
                return $transition;
            }

            $this->progression->cloneAcademicStructure($fromYear, $toYear);
            $summary = ['promoted' => 0, 'repeating' => 0, 'excluded' => 0, 'graduated' => 0];

            foreach ($this->progression->enrollmentsForYear($fromYear->school_id, $fromYear->id) as $enrollment) {
                $decision = $this->promotionEngine->evaluate($enrollment);
                if ($decision->final_decision === AcademicDecisionStatus::Excluded) {
                    $this->progression->markWithoutEnrollment($enrollment, EnrollmentStatus::Excluded);
                    $summary['excluded']++;

                    continue;
                }

                if (
                    $decision->final_decision === AcademicDecisionStatus::Promoted
                    && ($enrollment->schoolClass->is_terminal || SchoolClass::isTerminalLevel($enrollment->schoolClass->grade_level))
                ) {
                    $this->progression->markWithoutEnrollment($enrollment, EnrollmentStatus::Graduated);
                    $summary['graduated']++;

                    continue;
                }

                $repeating = $decision->final_decision === AcademicDecisionStatus::Repeating;
                $targetClass = $this->progression->targetClassFor($enrollment->schoolClass, $toYear, $repeating);
                if ($targetClass === null) {
                    throw ValidationException::withMessages([
                        'class_progression' => __('progression.errors.missing_target_class', ['class' => $enrollment->schoolClass->name]),
                    ]);
                }

                $this->progression->carryEnrollment(
                    $enrollment,
                    $targetClass,
                    $repeating ? EnrollmentStatus::Repeating : EnrollmentStatus::Completed,
                );
                $summary[$repeating ? 'repeating' : 'promoted']++;
            }

            $this->progression->activateAcademicYear($fromYear, $toYear);

            return $this->progression->completeTransition($transition, $summary);
        });
    }
}
