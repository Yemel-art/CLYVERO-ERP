<?php

declare(strict_types=1);

namespace App\Services\Progression;

use App\Enums\AcademicDecisionStatus;
use App\Models\AcademicDecision;
use App\Models\PromotionPolicy;
use App\Models\StudentEnrollment;
use App\Repositories\Contracts\ProgressionRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PromotionEngine extends BaseService
{
    public function __construct(private readonly ProgressionRepositoryInterface $progression) {}

    public function evaluate(StudentEnrollment $enrollment): AcademicDecision
    {
        $enrollment->loadMissing(['schoolClass.subjects']);
        $policy = $this->progression->policyForYear($enrollment->school_id, $enrollment->academic_year_id);
        if ($policy === null) {
            throw ValidationException::withMessages(['promotion_policy' => __('progression.errors.missing_policy')]);
        }

        $subjectResults = $this->subjectResults($enrollment, $policy);
        $expectedSubjectIds = $enrollment->schoolClass->subjects->pluck('id');
        $missingSubjectIds = $expectedSubjectIds->diff($subjectResults->keys())->values();
        if ($expectedSubjectIds->isEmpty() || $missingSubjectIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'grades' => __('progression.errors.incomplete_grades', ['count' => $missingSubjectIds->count()]),
            ]);
        }

        $totalCoefficient = (float) $subjectResults->sum('coefficient');
        $finalAverage = round(
            (float) $subjectResults->sum(fn (array $result): float => $result['average'] * $result['coefficient'])
                / max($totalCoefficient, 0.0001),
            2,
        );
        $failed = $subjectResults->filter(
            fn (array $result): bool => $result['average'] < (float) $policy->minimum_subject_mark,
        );
        $criticalFailures = $failed->keys()->intersect(collect($policy->critical_subject_ids ?? []))->values();
        $metrics = [
            'average' => $finalAverage,
            'failed_subjects' => $failed->count(),
            'critical_failures' => $criticalFailures->count(),
        ];
        $reasons = [];

        if ($this->conditionsMatch($policy->exclusion_conditions ?? [], $metrics)) {
            $decision = AcademicDecisionStatus::Excluded;
            $reasons[] = 'exclusion_conditions';
        } elseif (
            $finalAverage < (float) $policy->passing_average
            || $failed->count() > $policy->maximum_failed_subjects
            || $criticalFailures->isNotEmpty()
            || $this->conditionsMatch($policy->failure_conditions ?? [], $metrics)
        ) {
            $decision = AcademicDecisionStatus::Repeating;
            if ($finalAverage < (float) $policy->passing_average) {
                $reasons[] = 'average_below_pass_mark';
            }
            if ($failed->count() > $policy->maximum_failed_subjects) {
                $reasons[] = 'too_many_failed_subjects';
            }
            if ($criticalFailures->isNotEmpty()) {
                $reasons[] = 'critical_subject_failed';
            }
            if ($this->conditionsMatch($policy->failure_conditions ?? [], $metrics)) {
                $reasons[] = 'failure_conditions';
            }
        } else {
            $decision = AcademicDecisionStatus::Promoted;
            $reasons[] = 'promotion_requirements_met';
        }

        return $this->transaction(fn (): AcademicDecision => $this->progression->saveDecision(
            $enrollment,
            $policy,
            [
                'computed_decision' => $decision->value,
                'final_decision' => $decision->value,
                'final_average' => $finalAverage,
                'failed_subjects_count' => $failed->count(),
                'failed_subject_ids' => $failed->keys()->values()->all(),
                'decision_reasons' => $reasons,
                'finalized_at' => now(),
            ],
        ));
    }

    /** @return Collection<string, array{average:float, coefficient:float}> */
    private function subjectResults(StudentEnrollment $enrollment, PromotionPolicy $policy): Collection
    {
        $coefficients = $enrollment->schoolClass->subjects->mapWithKeys(
            fn ($subject): array => [
                $subject->id => max((float) ($subject->pivot->coefficient ?? $subject->coefficient ?? 1), 0.01),
            ],
        );

        return $this->progression->publishedGrades($enrollment)
            ->groupBy(fn ($entry) => $entry->assessment->subject_id)
            ->map(function (Collection $entries, string $subjectId) use ($coefficients, $policy): array {
                $weightedTotal = 0.0;
                $weightTotal = 0.0;
                foreach ($entries as $entry) {
                    $weight = (float) $entry->assessment->weight;
                    $weightedTotal += ((float) $entry->score / (float) $entry->assessment->max_score)
                        * (float) $policy->grading_scale * $weight;
                    $weightTotal += $weight;
                }

                return [
                    'average' => round($weightedTotal / max($weightTotal, 0.0001), 2),
                    'coefficient' => (float) $coefficients->get($subjectId, 1),
                ];
            });
    }

    /** @param array<string, mixed> $conditions @param array<string, float|int> $metrics */
    private function conditionsMatch(array $conditions, array $metrics): bool
    {
        if ($conditions === []) {
            return false;
        }

        $checks = [];
        if (isset($conditions['average_below'])) {
            $checks[] = $metrics['average'] < (float) $conditions['average_below'];
        }
        if (isset($conditions['failed_subjects_at_least'])) {
            $checks[] = $metrics['failed_subjects'] >= (int) $conditions['failed_subjects_at_least'];
        }
        if (isset($conditions['critical_failures_at_least'])) {
            $checks[] = $metrics['critical_failures'] >= (int) $conditions['critical_failures_at_least'];
        }
        if ($checks === []) {
            return false;
        }

        return ($conditions['match'] ?? 'any') === 'all'
            ? ! in_array(false, $checks, true)
            : in_array(true, $checks, true);
    }
}
