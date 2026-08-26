<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Reports\HonorRollRequest;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Services\HonorRoll\HonorRollService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class HonorRollController extends ApiController
{
    public function __construct(private readonly HonorRollService $honorRoll)
    {
    }

    public function data(HonorRollRequest $request): JsonResponse
    {
        $year = AcademicYear::findOrFail($request->string('academic_year_id')->toString());
        $term = Term::findOrFail($request->string('term_id')->toString());

        return $this->ok($this->honorRoll->build(
            $year,
            $term,
            $request->input('class_id'),
            $request->string('language', 'fr')->toString(),
        ), 'Honor roll generated.');
    }

    public function download(HonorRollRequest $request): Response
    {
        $year = AcademicYear::findOrFail($request->string('academic_year_id')->toString());
        $term = Term::findOrFail($request->string('term_id')->toString());

        return $this->honorRoll->download(
            $year,
            $term,
            $request->input('class_id'),
            $request->string('language', 'fr')->toString(),
        );
    }
}
