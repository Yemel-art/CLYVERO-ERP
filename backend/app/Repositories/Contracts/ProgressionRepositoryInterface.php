<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicDecision;
use App\Models\AcademicYear;
use App\Models\PromotionPolicy;
use App\Models\SchoolClass;
use App\Models\SchoolYearTransition;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface ProgressionRepositoryInterface
{
    public function policyForYear(string $schoolId, string $academicYearId): ?PromotionPolicy;

    /** @return Collection<int, StudentEnrollment> */
    public function enrollmentsForYear(string $schoolId, string $academicYearId): Collection;

    /** @return SupportCollection<int, object> */
    public function publishedGrades(StudentEnrollment $enrollment): SupportCollection;

    public function saveDecision(StudentEnrollment $enrollment, PromotionPolicy $policy, array $attributes): AcademicDecision;

    /** @return Collection<int, AcademicDecision> */
    public function decisionsForYear(string $schoolId, string $academicYearId): Collection;

    public function syncEnrollment(Student $student): ?StudentEnrollment;

    /** @return array<string, SchoolClass> keyed by source class ID */
    public function cloneAcademicStructure(AcademicYear $fromYear, AcademicYear $toYear): array;

    public function targetClassFor(SchoolClass $sourceClass, AcademicYear $toYear, bool $repeat): ?SchoolClass;

    public function carryEnrollment(StudentEnrollment $source, SchoolClass $target, EnrollmentStatus $sourceStatus): StudentEnrollment;

    public function markWithoutEnrollment(StudentEnrollment $source, EnrollmentStatus $status): void;

    public function startTransition(AcademicYear $fromYear, AcademicYear $toYear, ?string $userId): SchoolYearTransition;

    public function completeTransition(SchoolYearTransition $transition, array $summary): SchoolYearTransition;

    public function activateAcademicYear(AcademicYear $fromYear, AcademicYear $toYear): void;

    public function savePolicy(string $schoolId, ?string $academicYearId, array $attributes, ?string $userId): PromotionPolicy;

    public function decision(string $decisionId): AcademicDecision;

    public function overrideDecision(AcademicDecision $decision, array $attributes): AcademicDecision;
}
