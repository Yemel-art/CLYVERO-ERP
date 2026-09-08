<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Progression\SavePromotionPolicyRequest;
use App\Http\Requests\Progression\UpdateAcademicDecisionRequest;
use App\Http\Resources\AcademicDecisionResource;
use App\Http\Resources\PromotionPolicyResource;
use App\Models\AcademicYear;
use App\Services\Progression\ProgressionManagementService;
use App\Services\Progression\SchoolYearTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProgressionController extends ApiController
{
    public function __construct(
        private readonly ProgressionManagementService $progression,
        private readonly SchoolYearTransitionService $transitions,
    ) {}

    public function policy(Request $request, AcademicYear $year): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('settings.view'), 403);

        $policy = $this->progression->policy($year);

        return $this->ok(
            $policy ? new PromotionPolicyResource($policy) : null,
            __('progression.messages.policy_retrieved'),
        );
    }

    public function savePolicy(SavePromotionPolicyRequest $request, AcademicYear $year): JsonResponse
    {
        return $this->ok(
            new PromotionPolicyResource($this->progression->savePolicy($year, $request->validated())),
            __('progression.messages.policy_saved'),
        );
    }

    public function decisions(Request $request, AcademicYear $year): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('grade.view'), 403);

        return $this->ok(
            AcademicDecisionResource::collection($this->progression->decisions($year)),
            __('progression.messages.decisions_retrieved'),
        );
    }

    public function evaluate(Request $request, AcademicYear $year): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('grade.edit'), 403);

        return $this->ok(
            AcademicDecisionResource::collection($this->progression->evaluateYear($year)),
            __('progression.messages.decisions_generated'),
        );
    }

    public function updateDecision(UpdateAcademicDecisionRequest $request, string $decisionId): JsonResponse
    {
        return $this->ok(
            new AcademicDecisionResource($this->progression->updateDecision($decisionId, $request->validated())),
            __('progression.messages.decision_updated'),
        );
    }

    public function rollover(Request $request, AcademicYear $fromYear, AcademicYear $toYear): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('academic_year.edit'), 403);

        return $this->ok(
            $this->transitions->rollover($fromYear, $toYear),
            __('progression.messages.rollover_completed'),
        );
    }
}
