<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AcademicYear;
use App\Models\CarteScolaireImport;
use App\Services\Import\CarteScolaireImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CarteScolaireImportController extends ApiController
{
    public function __construct(private readonly CarteScolaireImportService $imports) {}

    public function preview(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'academic_year_id' => ['required', 'uuid'],
            'cycle' => ['required', Rule::in(['secondary_general', 'secondary_technical'])],
        ]);
        $year = AcademicYear::query()->findOrFail($data['academic_year_id']);
        $result = $this->imports->preview($request->file('file'), $year, $request->user(), $data['cycle']);
        return $this->created($result, 'Import preview created. Review it before approving.');
    }

    public function approve(Request $request, CarteScolaireImport $import): JsonResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        return $this->ok($this->imports->approveAndImport($import, $request->user()), 'Import approved and completed.');
    }
}
