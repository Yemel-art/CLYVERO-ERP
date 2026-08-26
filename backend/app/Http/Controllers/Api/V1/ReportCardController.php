<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Student;
use App\Models\Term;
use App\Services\ReportCard\ReportCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReportCardController extends ApiController
{
    public function __construct(
        private readonly ReportCardService $reports,
    ) {
    }

    public function data(Request $request, string $studentId, string $termId): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('report_card.view'), 403);
        /** @var Student $student */
        $student = Student::findOrFail($studentId);
        $this->authorize('view', $student);
        /** @var Term $term */
        $term = Term::findOrFail($termId);

        return $this->ok($this->reports->buildData($student, $term), 'Report card data.');
    }

    public function download(Request $request, string $studentId, string $termId): Response
    {
        abort_unless($request->user()?->hasPermission('report_card.view'), 403);
        /** @var Student $student */
        $student = Student::findOrFail($studentId);
        $this->authorize('view', $student);
        /** @var Term $term */
        $term = Term::findOrFail($termId);

        return $this->reports->downloadPdf($student, $term);
    }

    public function preview(Request $request, string $studentId, string $termId): Response
    {
        abort_unless($request->user()?->hasPermission('report_card.view'), 403);
        /** @var Student $student */
        $student = Student::findOrFail($studentId);
        $this->authorize('view', $student);
        /** @var Term $term */
        $term = Term::findOrFail($termId);

        return $this->reports->streamPdf($student, $term);
    }
}
