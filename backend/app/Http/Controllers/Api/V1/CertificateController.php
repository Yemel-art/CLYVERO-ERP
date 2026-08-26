<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Reports\GenerateCertificateRequest;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Documents\CertificateService;
use Symfony\Component\HttpFoundation\Response;

final class CertificateController extends ApiController
{
    public function __construct(private readonly CertificateService $certificates)
    {
    }

    public function employment(GenerateCertificateRequest $request, Teacher $teacher): Response
    {
        return $this->certificates->employmentCertificate(
            $teacher,
            $request->string('language', 'fr')->toString(),
        );
    }

    public function school(GenerateCertificateRequest $request, Student $student): Response
    {
        /** @var AcademicYear $academicYear */
        $academicYear = AcademicYear::findOrFail($request->string('academic_year_id')->toString());

        return $this->certificates->schoolCertificate(
            $student,
            $academicYear,
            $request->string('language', 'fr')->toString(),
        );
    }
}
