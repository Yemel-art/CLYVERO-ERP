<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\BaseService;
use App\Services\Academic\DefaultAcademicTerms;
use App\Services\Academic\DefaultSecondaryCurriculum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class SchoolProvisioningService extends BaseService
{
    public function __construct(
        private readonly DefaultSecondaryCurriculum $curriculum,
        private readonly DefaultAcademicTerms $defaultTerms,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): School
    {
        return $this->transaction(function () use ($attributes): School {
            DB::select("SELECT pg_advisory_xact_lock(hashtext('clyvero-school-code'))");

            $code = ($attributes['school_code'] ?? null) ?: $this->nextSchoolCode();
            $slug = ($attributes['slug'] ?? null) ?: $this->uniqueSlug((string) $attributes['school_name']);
            $school = School::query()->create([
                'school_name' => $attributes['school_name'],
                'school_code' => $code,
                'slug' => $slug,
                'email' => $attributes['email'],
                'phone' => $attributes['phone'] ?? null,
                'address' => $attributes['address'] ?? null,
                'city' => $attributes['city'] ?? null,
                'country' => $attributes['country'] ?? 'Cameroon',
                'default_locale' => $attributes['default_locale'],
                'education_systems' => array_values(array_unique($attributes['education_systems'])),
                'is_active' => true,
            ]);

            [$startYear, $endYear] = $this->currentAcademicYearWindow();
            $year = AcademicYear::withoutGlobalScopes()->create([
                'school_id' => $school->id,
                'title' => "{$startYear}-{$endYear}",
                'start_date' => "{$startYear}-09-01",
                'end_date' => "{$endYear}-06-30",
                'status' => AcademicYear::STATUS_ACTIVE,
            ]);
            $school->update(['current_academic_year_id' => $year->id]);
            $this->defaultTerms->provision($year);
            $this->curriculum->provision($school);

            $role = Role::query()->where('name', UserRole::Administrator->value)->firstOrFail();
            User::withoutGlobalScopes()->create([
                'school_id' => $school->id,
                'role_id' => $role->id,
                'first_name' => $attributes['administrator_first_name'],
                'last_name' => $attributes['administrator_last_name'],
                'email' => $attributes['administrator_email'],
                'password' => Hash::make($attributes['administrator_password']),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            return $school->fresh(['currentAcademicYear']) ?? $school;
        });
    }

    public function setActive(School $school, bool $active): School
    {
        return $this->transaction(function () use ($school, $active): School {
            /** @var School $locked */
            $locked = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();
            $locked->update(['is_active' => $active]);

            if (! $active) {
                User::withoutGlobalScopes()
                    ->where('school_id', $locked->id)
                    ->eachById(static function (User $user): void {
                        $user->tokens()->delete();
                    });
            }

            return $locked->fresh(['currentAcademicYear']) ?? $locked;
        });
    }

    public function administratorFor(School $school): User
    {
        return User::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereHas('role', fn ($query) => $query->where('name', UserRole::Administrator->value))
            ->oldest('created_at')
            ->firstOrFail();
    }

    /** @param array<string, mixed> $attributes */
    public function updateAdministratorCredentials(School $school, array $attributes): User
    {
        return $this->transaction(function () use ($school, $attributes): User {
            $administratorId = $this->administratorFor($school)->id;
            /** @var User $administrator */
            $administrator = User::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->whereKey($administratorId)
                ->lockForUpdate()
                ->firstOrFail();

            $updates = [
                'first_name' => $attributes['first_name'],
                'last_name' => $attributes['last_name'],
                'email' => strtolower(trim((string) $attributes['email'])),
                'email_verified_at' => now(),
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'is_active' => true,
            ];
            if (! empty($attributes['administrator_password'])) {
                $updates['password'] = Hash::make((string) $attributes['administrator_password']);
            }

            $administrator->forceFill($updates)->save();
            $administrator->tokens()->delete();

            if (Schema::hasTable('admin_login_otp_challenges')) {
                DB::table('admin_login_otp_challenges')->where('user_id', $administrator->id)->delete();
            }
            if (Schema::hasTable('tenant_password_reset_challenges')) {
                DB::table('tenant_password_reset_challenges')->where('user_id', $administrator->id)->delete();
            }

            return $administrator->fresh(['role']) ?? $administrator;
        });
    }

    /** @return array{int, int} */
    private function currentAcademicYearWindow(): array
    {
        $today = now();
        // January-June belongs to the year that began the previous September;
        // July-August provisions the upcoming September intake.
        $startYear = $today->month >= 7 ? $today->year : $today->year - 1;

        return [$startYear, $startYear + 1];
    }

    private function nextSchoolCode(): string
    {
        $highest = School::query()->where('school_code', 'like', 'CLY-%')
            ->orderByDesc('school_code')->value('school_code');
        $next = $highest && preg_match('/^CLY-(\d+)$/', $highest, $matches)
            ? ((int) $matches[1]) + 1
            : 1;

        return sprintf('CLY-%06d', $next);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'school';
        $slug = $base;
        $suffix = 2;
        while (School::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
