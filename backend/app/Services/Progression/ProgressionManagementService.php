<?php

declare(strict_types=1);

namespace App\Services\Progression;

use App\Models\AcademicDecision;
use App\Models\AcademicYear;
use App\Models\PromotionPolicy;
use App\Repositories\Contracts\ProgressionRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class ProgressionManagementService
{
    public function __construct(
        private readonly ProgressionRepositoryInterface $progression,
        private readonly PromotionEngine $engine,
    ) {}

    public function policy(AcademicYear $year): ?PromotionPolicy
    {
        return $this->progression->policyForYear($year->school_id, $year->id);
    }

    public function savePolicy(AcademicYear $year, array $attributes): PromotionPolicy
    {
        return $this->progression->savePolicy($year->school_id, $year->id, $attributes, Auth::id());
    }

    /** @return Collection<int, AcademicDecision> */
    public function decisions(AcademicYear $year): Collection
    {
        return $this->progression->decisionsForYear($year->school_id, $year->id);
    }

    /** @return array<int, AcademicDecision> */
    public function evaluateYear(AcademicYear $year): array
    {
        $decisions = [];
        foreach ($this->progression->enrollmentsForYear($year->school_id, $year->id) as $enrollment) {
            $decisions[] = $this->engine->evaluate($enrollment);
        }

        return $decisions;
    }

    public function updateDecision(string $decisionId, array $attributes): AcademicDecision
    {
        $decision = $this->progression->decision($decisionId);
        if (isset($attributes['final_decision'])) {
            if (! $decision->promotionPolicy->allow_class_council_override) {
                throw ValidationException::withMessages(['final_decision' => __('progression.errors.override_disabled')]);
            }
            $attributes['overridden_by'] = Auth::id();
            $attributes['overridden_at'] = now();
        }

        return $this->progression->overrideDecision($decision, $attributes);
    }
}
