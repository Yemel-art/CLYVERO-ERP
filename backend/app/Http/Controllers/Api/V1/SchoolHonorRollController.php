<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Reports\HonorRollRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\SchoolHonorRollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class SchoolHonorRollController extends ApiController
{
    public function __construct(private readonly SchoolHonorRollService $honorRoll) {}

    public function data(HonorRollRequest $request): JsonResponse
    {
        [$year, $term] = $this->selection($request);
        return $this->ok($this->honorRoll->build(
            $year, $term, $request->input('class_id'),
            $request->string('language', 'fr')->toString(),
        ), 'Honor roll generated.');
    }

    public function download(HonorRollRequest $request): Response
    {
        [$year, $term] = $this->selection($request);
        return $this->honorRoll->download(
            $year, $term, $request->input('class_id'),
            $request->string('language', 'fr')->toString(),
        );
    }

    /** @return array{AcademicYear, Term} */
    private function selection(HonorRollRequest $request): array
    {
        $year = AcademicYear::findOrFail($request->string('academic_year_id')->toString());
        $term = Term::findOrFail($request->string('term_id')->toString());
        if ($term->academic_year_id !== $year->id) {
            throw ValidationException::withMessages(['term_id' => ['The term does not belong to this academic year.']]);
        }
        $classId = $request->input('class_id');
        if ($classId && ! SchoolClass::where('id', $classId)->where('academic_year_id', $year->id)->exists()) {
            throw ValidationException::withMessages(['class_id' => ['The class does not belong to this academic year.']]);
        }
        return [$year, $term];
    }
}
