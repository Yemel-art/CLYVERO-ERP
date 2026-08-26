<?php

declare(strict_types=1);

namespace App\Services\ParentGuardian;

use App\DTO\ParentGuardian\ParentGuardianDTO;
use App\Enums\UserRole;
use App\Models\ParentGuardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Repositories\Contracts\ParentRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ParentService extends BaseService
{
    public function __construct(
        private readonly ParentRepositoryInterface $parents,
    ) {}

    public function register(ParentGuardianDTO $dto): ParentGuardian
    {
        return $this->transaction(function () use ($dto): ParentGuardian {
            $userId = null;
            $tempPassword = null;
            if ($dto->createUserAccount) {
                $parentRole = Role::where('name', UserRole::Parent->value)->firstOrFail();
                $tempPassword = $this->generateTempPassword();
                $user = User::create([
                    'role_id' => $parentRole->id,
                    'first_name' => $dto->firstName,
                    'last_name' => $dto->lastName,
                    'email' => strtolower($dto->email),
                    'phone' => $dto->phone,
                    'password' => Hash::make($tempPassword),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
                $userId = $user->id;
                // TODO: dispatch WelcomeParentMail with temp password (Phase 12).
            }

            $attributes = $dto->toAttributes() + [
                'user_id' => $userId,
                'created_by' => Auth::id(),
            ];

            /** @var ParentGuardian $parent */
            $parent = $this->parents->create($attributes);

            $this->syncChildren($parent, $dto->children);

            $parent->load(['user', 'students']);
            if ($tempPassword !== null) {
                $parent->setAttribute('temporary_password', $tempPassword);
            }

            return $parent;
        });
    }

    public function update(ParentGuardian $parent, ParentGuardianDTO $dto): ParentGuardian
    {
        return $this->transaction(function () use ($parent, $dto): ParentGuardian {
            $parent = $this->parents->update($parent, $dto->toAttributes());
            if ($parent->user_id) {
                User::where('id', $parent->user_id)->update([
                    'first_name' => $dto->firstName,
                    'last_name' => $dto->lastName,
                    'email' => strtolower($dto->email),
                    'phone' => $dto->phone,
                ]);
            }
            if (! empty($dto->children)) {
                $this->syncChildren($parent, $dto->children);
            }

            return $parent->load(['user', 'students']);
        });
    }

    public function archive(ParentGuardian $parent): ParentGuardian
    {
        return $this->transaction(function () use ($parent): ParentGuardian {
            $parent->is_active = false;
            $parent->save();
            if ($parent->user_id) {
                User::where('id', $parent->user_id)->update(['is_active' => false]);
            }
            $parent->delete();

            return $parent->fresh() ?? $parent;
        });
    }

    public function restore(ParentGuardian $parent): ParentGuardian
    {
        return $this->transaction(function () use ($parent): ParentGuardian {
            $parent->restore();
            $parent->is_active = true;
            $parent->save();
            if ($parent->user_id) {
                User::where('id', $parent->user_id)->update(['is_active' => true]);
            }

            return $parent->fresh() ?? $parent;
        });
    }

    /**
     * Attach a child to this parent.
     *
     * @param  array{relationship:string, is_primary?:bool, can_pickup?:bool}  $pivot
     */
    public function attachChild(ParentGuardian $parent, Student $student, array $pivot): ParentGuardian
    {
        return $this->transaction(function () use ($parent, $student, $pivot): ParentGuardian {
            $parent->students()->syncWithoutDetaching([
                $student->id => [
                    'id' => (string) Str::uuid(),
                    'relationship' => $pivot['relationship'],
                    'is_primary' => (bool) ($pivot['is_primary'] ?? false),
                    'can_pickup' => (bool) ($pivot['can_pickup'] ?? true),
                ],
            ]);
            // If this is now the primary, also write the legacy student.parent_id for fast lookups.
            if (! empty($pivot['is_primary'])) {
                Student::where('id', $student->id)->update(['parent_id' => $parent->id]);
            }

            return $parent->load('students');
        });
    }

    public function detachChild(ParentGuardian $parent, Student $student): ParentGuardian
    {
        return $this->transaction(function () use ($parent, $student): ParentGuardian {
            $parent->students()->detach($student->id);
            // If this parent was the primary for that student, clear the legacy pointer.
            if ($student->parent_id === $parent->id) {
                Student::where('id', $student->id)->update(['parent_id' => null]);
            }

            return $parent->load('students');
        });
    }

    /**
     * @param  array<int, array{student_id:string, relationship:string, is_primary?:bool, can_pickup?:bool}>  $children
     */
    private function syncChildren(ParentGuardian $parent, array $children): void
    {
        if (empty($children)) {
            return;
        }
        $sync = [];
        $primaryStudentId = null;
        foreach ($children as $c) {
            $sync[$c['student_id']] = [
                'id' => (string) Str::uuid(),
                'relationship' => $c['relationship'],
                'is_primary' => (bool) ($c['is_primary'] ?? false),
                'can_pickup' => (bool) ($c['can_pickup'] ?? true),
            ];
            if (! empty($c['is_primary'])) {
                $primaryStudentId = $c['student_id'];
            }
        }
        $parent->students()->sync($sync);

        if ($primaryStudentId !== null) {
            Student::where('id', $primaryStudentId)->update(['parent_id' => $parent->id]);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $with
     */
    public function list(array $filters, array $with = [], int $perPage = 20, string $sortBy = 'last_name', string $sortOrder = 'asc'): LengthAwarePaginator
    {
        return $this->parents->searchPaginated($filters, $with, $perPage, $sortBy, $sortOrder);
    }

    /** @return array<string, int> */
    public function statistics(): array
    {
        $total = $this->parents->countActive();

        return [
            'total' => $total,
            'with_login' => ParentGuardian::query()->whereNotNull('user_id')->count(),
            'archived' => ParentGuardian::onlyTrashed()->count(),
        ];
    }

    private function generateTempPassword(): string
    {
        return 'TL@'.Str::random(10).rand(0, 9);
    }
}
