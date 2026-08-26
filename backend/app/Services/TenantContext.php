<?php

declare(strict_types=1);

namespace App\Services;

final class TenantContext
{
    private ?string $schoolId = null;
    private ?string $schoolCode = null;

    public function setSchoolId(string $schoolId): void
    {
        $this->schoolId = $schoolId;
    }

    public function schoolId(): ?string
    {
        return $this->schoolId;
    }

    public function hasSchool(): bool
    {
        return $this->schoolId !== null;
    }

    public function schoolCode(): string
    {
        if ($this->schoolCode !== null) {
            return $this->schoolCode;
        }

        $school = \App\Models\School::query()->whereKey($this->schoolId)->first(['school_code', 'slug']);
        $this->schoolCode = strtoupper((string) ($school?->school_code ?: $school?->slug ?: 'SCHOOL'));

        return $this->schoolCode;
    }

    public function clear(): void
    {
        $this->schoolId = null;
        $this->schoolCode = null;
    }
}
