<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\TeacherStatus;
use App\Traits\Auditable;
use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property string $id
 * @property ?string $user_id
 * @property string $employee_number
 * @property string $first_name
 * @property string $last_name
 * @property ?string $middle_name
 * @property Gender $gender
 * @property ?Carbon $date_of_birth
 * @property string $nationality
 * @property ?string $photo
 * @property string $email
 * @property ?string $phone
 * @property ?string $address
 * @property ?string $city
 * @property string $country
 * @property ?string $qualification
 * @property ?string $specialization
 * @property int $years_of_experience
 * @property Carbon $hire_date
 * @property TeacherStatus $status
 */
class Teacher extends Model
{
    use Auditable;
    use BelongsToSchool;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public string $auditModule = 'teacher';

    protected $fillable = [
        'school_id', 'user_id', 'employee_number',
        'first_name', 'last_name', 'middle_name',
        'gender', 'date_of_birth', 'nationality', 'photo',
        'email', 'phone', 'address', 'city', 'country',
        'qualification', 'specialization', 'position', 'department', 'years_of_experience',
        'hire_date', 'salary',
        'emergency_contact_name', 'emergency_contact_phone',
        'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'gender'              => Gender::class,
            'status'              => TeacherStatus::class,
            'date_of_birth'       => 'date',
            'hire_date'           => 'date',
            'years_of_experience' => 'integer',
            'salary'              => 'decimal:2',
        ];
    }

    /** Never log salary or photo changes. */
    public function auditExcludedAttributes(): array
    {
        return ['updated_at', 'photo', 'salary'];
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }


    public function classesTaught(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject', 'teacher_id', 'class_id')
            ->withPivot(['subject_id', 'coefficient', 'weekly_frequency'])
            ->withTimestamps();
    }

    public function formMasterOf(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'form_master_id');
    }

    /**
     * Resolve the staff profile belonging to a login account.
     *
     * Older data can contain a teacher-role user and a teacher profile with
     * the same school/email but no user_id link. Repair that safe one-to-one
     * match so all teacher portal permissions use the same profile.
     */
    public static function resolveForUser(User $user): ?self
    {
        $teacher = static::query()->where('user_id', $user->id)->first();
        if ($teacher !== null || ! $user->isTeacher()) {
            return $teacher;
        }

        $teacher = static::query()
            ->whereNull('user_id')
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($user->email))])
            ->first();

        if ($teacher !== null) {
            $teacher->forceFill(['user_id' => $user->id])->saveQuietly();
        }

        return $teacher;
    }

    // ─── Accessors ──────────────────────────────────────────────────

    public function fullName(): Attribute
    {
        return Attribute::get(
            fn (): string => trim("{$this->first_name} {$this->middle_name} {$this->last_name}")
        );
    }

    public function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo
            ? URL::temporarySignedRoute('public.media', now()->addMinutes(30), [
                'directory' => 'teachers',
                'filename' => basename($this->photo),
            ], false)
            : null);
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', TeacherStatus::Active->value);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
        return $q->where(function (Builder $q) use ($like): void {
            $q->where('first_name', 'ilike', $like)
                ->orWhere('last_name', 'ilike', $like)
                ->orWhere('employee_number', 'ilike', $like)
                ->orWhere('email', 'ilike', $like);
        });
    }
}
