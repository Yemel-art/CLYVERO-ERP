<?php

declare(strict_types=1);

namespace App\Services\Teacher;

use App\DTO\Teacher\TeacherDTO;
use App\Enums\TeacherStatus;
use App\Enums\UserRole;
use App\Exceptions\PhotoUploadException;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use App\Repositories\Contracts\TeacherRepositoryInterface;
use App\Services\BaseService;
use App\Services\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class TeacherService extends BaseService
{
    private const PHOTO_DISK = 'public';
    private const PHOTO_DIR  = 'teachers';
    private const PHOTO_SIZE = 400;

    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {
    }

    /**
     * @return array{teacher: Teacher, temporary_password: ?string}
     */
    public function register(TeacherDTO $dto, ?UploadedFile $photo = null): array
    {
        return $this->transaction(function () use ($dto, $photo): array {
            $year = (int) now()->year;
            $schoolId = Auth::user()?->school_id ?? app(TenantContext::class)->schoolId();
            $email = strtolower(trim($dto->email));

            $userId = null;
            $tempPassword = null;
            if ($dto->createUserAccount) {
                $teacherRole  = Role::where('name', UserRole::Teacher->value)->firstOrFail();
                $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

                if ($user !== null) {
                    if (! $user->isTeacher() || Teacher::query()->where('user_id', $user->id)->exists()) {
                        throw ValidationException::withMessages([
                            'email' => ['This email is already used by another account in this school.'],
                        ]);
                    }

                    $user->forceFill([
                        'role_id' => $teacherRole->id,
                        'first_name' => $dto->firstName,
                        'last_name' => $dto->lastName,
                        'phone' => $dto->phone,
                        'is_active' => true,
                    ])->save();
                } else {
                    $tempPassword = $this->generateTempPassword();
                    $user = User::create([
                        'school_id'  => $schoolId,
                        'role_id'    => $teacherRole->id,
                        'first_name' => $dto->firstName,
                        'last_name'  => $dto->lastName,
                        'email'      => $email,
                        'phone'      => $dto->phone,
                        'password'   => Hash::make($tempPassword),
                        'is_active'  => true,
                        'email_verified_at' => now(),
                    ]);
                }
                $userId = $user->id;
            }

            $attributes = $dto->toAttributes() + [
                'school_id'       => $schoolId,
                'user_id'         => $userId,
                'employee_number' => $this->teachers->nextEmployeeNumber($year),
                'status'          => TeacherStatus::Active->value,
                'created_by'      => Auth::id(),
            ];

            /** @var Teacher $teacher */
            $teacher = $this->teachers->create($attributes);

            if ($photo !== null) {
                $this->setPhoto($teacher, $photo);
            }

            return [
                'teacher' => $teacher->load(['user', 'createdBy']),
                'temporary_password' => $tempPassword,
            ];
        });
    }

    public function update(Teacher $teacher, TeacherDTO $dto): Teacher
    {
        return $this->transaction(function () use ($teacher, $dto): Teacher {
            $teacher = $this->teachers->update($teacher, $dto->toAttributes());

            // Keep the linked user account in sync.
            if ($teacher->user_id) {
                User::where('id', $teacher->user_id)->update([
                    'first_name' => $dto->firstName,
                    'last_name'  => $dto->lastName,
                    'email'      => $dto->email,
                    'phone'      => $dto->phone,
                ]);
            }

            return $teacher->load(['user', 'createdBy']);
        });
    }

    public function archive(Teacher $teacher): Teacher
    {
        return $this->transaction(function () use ($teacher): Teacher {
            $teacher->status = TeacherStatus::Archived;
            $teacher->save();
            if ($teacher->user_id) {
                User::where('id', $teacher->user_id)->update(['is_active' => false]);
            }
            $teacher->delete();
            return $teacher->fresh() ?? $teacher;
        });
    }

    public function restore(Teacher $teacher): Teacher
    {
        return $this->transaction(function () use ($teacher): Teacher {
            $teacher->restore();
            $teacher->status = TeacherStatus::Active;
            $teacher->save();
            if ($teacher->user_id) {
                User::where('id', $teacher->user_id)->update(['is_active' => true]);
            }
            return $teacher->fresh() ?? $teacher;
        });
    }

    public function setPhoto(Teacher $teacher, UploadedFile $photo): Teacher
    {
        try {
            $processed = Image::read($photo->getRealPath())
                ->cover(self::PHOTO_SIZE, self::PHOTO_SIZE)
                ->toJpeg(85);
        } catch (Throwable $e) {
            throw new PhotoUploadException('Image could not be processed: ' . $e->getMessage());
        }

        $path = self::PHOTO_DIR . '/' . $teacher->id . '-' . Str::random(8) . '.jpg';
        if (! Storage::disk(self::PHOTO_DISK)->put($path, (string) $processed)) {
            throw new PhotoUploadException('The processed image could not be written to public storage.');
        }

        if ($teacher->photo && Storage::disk(self::PHOTO_DISK)->exists($teacher->photo)) {
            Storage::disk(self::PHOTO_DISK)->delete($teacher->photo);
        }

        $teacher->photo = $path;
        $teacher->save();
        return $teacher->refresh();
    }

    public function removePhoto(Teacher $teacher): Teacher
    {
        if ($teacher->photo && Storage::disk(self::PHOTO_DISK)->exists($teacher->photo)) {
            Storage::disk(self::PHOTO_DISK)->delete($teacher->photo);
        }
        $teacher->photo = null;
        $teacher->save();
        return $teacher->refresh();
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, string>   $with
     */
    public function list(array $filters, array $with = [], int $perPage = 20, string $sortBy = 'last_name', string $sortOrder = 'asc'): LengthAwarePaginator
    {
        return $this->teachers->searchPaginated($filters, $with, $perPage, $sortBy, $sortOrder);
    }

    /** @return array<string, int> */
    public function statistics(): array
    {
        return [
            'total'      => $this->teachers->countByStatus(TeacherStatus::Active->value),
            'on_leave'   => $this->teachers->countByStatus(TeacherStatus::OnLeave->value),
            'archived'   => $this->teachers->countByStatus(TeacherStatus::Archived->value),
            'terminated' => $this->teachers->countByStatus(TeacherStatus::Terminated->value),
        ];
    }

    private function generateTempPassword(): string
    {
        // 14-char password meeting the StrongPassword rule.
        $core = Str::random(10);
        return 'TL@' . $core . rand(0, 9);
    }
}
