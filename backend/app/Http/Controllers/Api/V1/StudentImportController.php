<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Student\ConfirmOfficialStudentImportRequest;
use App\Http\Requests\Student\AnalyzeStudentImportRequest;
use App\Http\Requests\Student\PreviewOfficialStudentImportRequest;
use App\Http\Resources\StudentImportResource;
use App\Models\AcademicYear;
use App\Models\StudentImport;
use App\Services\Student\OfficialStudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentImportController extends ApiController
{
    public function __construct(private readonly OfficialStudentImportService $imports) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(($user?->hasPermission('student.view') ?? false) && ($user->isAdministrator() || $user->isSecretary()), 403);
        $page = StudentImport::query()
            ->with('academicYear')
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return $this->ok(StudentImportResource::collection($page->items()), 'Student imports retrieved.', meta: [
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function show(Request $request, StudentImport $studentImport): JsonResponse
    {
        $user = $request->user();
        abort_unless(($user?->hasPermission('student.view') ?? false) && ($user->isAdministrator() || $user->isSecretary()), 403);

        return $this->ok(new StudentImportResource($studentImport->load(['academicYear', 'rows'])), 'Student import retrieved.');
    }

    public function preview(PreviewOfficialStudentImportRequest $request): JsonResponse
    {
        $year = $this->activeYear();
        if ($request->filled('academic_year_id') && $request->string('academic_year_id')->toString() !== $year->id) {
            abort(422, 'Student imports must use the currently active academic year.');
        }
        $import = $this->imports->preview($request->file('file'), $year, $request->validated('column_mapping', []));

        return $this->created(new StudentImportResource($import->load(['academicYear', 'rows'])), 'Import file validated. Review the rows and map its classes before confirmation.');
    }

    public function analyze(AnalyzeStudentImportRequest $request): JsonResponse
    {
        return $this->ok(
            $this->imports->analyze($request->file('file'), $this->activeYear()),
            'Spreadsheet columns detected. Map them before previewing the import.',
        );
    }

    public function confirm(ConfirmOfficialStudentImportRequest $request, StudentImport $studentImport): JsonResponse
    {
        $mapping = collect($request->validated('class_mapping'))
            ->mapWithKeys(fn (array $item): array => [(string) $item['source_class'] => (string) $item['class_id']])
            ->all();
        $import = $this->imports->confirm($studentImport, $mapping, (string) $request->validated('duplicate_action', 'skip'));

        return $this->ok(new StudentImportResource($import->load(['academicYear', 'rows'])), 'Students imported successfully.');
    }

    private function activeYear(): AcademicYear
    {
        $year = AcademicYear::query()->active()->first();
        if (! $year) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'academic_year' => ['No active academic year is configured for this school.'],
            ]);
        }

        return $year;
    }
}
