<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentCycle;
use App\Enums\StudentStatus;
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
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

/**
 * Student record.
 *
 * Phase 2 model. `parent_id` and `class_id` are nullable UUID columns
 * without FK constraints; their relationships are wired up in Phase 4
 * (parents) and Phase 5 (classes) by introducing new relationship methods.
 *
 * @property string $id
 * @property string $admission_number
 * @property string $first_name
 * @property string $last_name
 * @property ?string $middle_name
 * @property Gender $gender
 * @property Carbon $date_of_birth
 * @property ?string $place_of_birth
 * @property string $nationality
 * @property ?string $religion
 * @property ?string $photo
 * @property ?string $email
 * @property ?string $phone
 * @property ?string $address
 * @property ?string $city
 * @property string $country
 * @property ?string $parent_id
 * @property ?string $class_id
 * @property ?string $academic_year_id
 * @property Carbon $enrollment_date
 * @property ?string $previous_school
 * @property string $cycle
 * @property ?string $speciality
 * @property ?string $emergency_contact_name
 * @property ?string $emergency_contact_phone
 * @property ?string $emergency_contact_relationship
 * @property ?string $blood_group
 * @property ?string $allergies
 * @property ?string $medical_conditions
 * @property StudentStatus $status
 * @property ?string $created_by
 */
class Student extends Model
{
    use Auditable;
    use BelongsToSchool;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /** Module identifier used by the AuditObserver. */
    public string $auditModule = 'student';

    protected $fillable = [
        'school_id', 'admission_number', 'official_matricule',
        'first_name', 'last_name', 'middle_name',
        'gender', 'date_of_birth', 'place_of_birth',
        'nationality', 'religion', 'photo',
        'email', 'phone',
        'address', 'city', 'country',
        'parent_id', 'class_id', 'academic_year_id',
        'enrollment_date', 'previous_school',
        'cycle', 'speciality',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
        'blood_group', 'allergies', 'medical_conditions',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'gender'          => Gender::class,
            'status'          => StudentStatus::class,
            'cycle'           => StudentCycle::class,
            'date_of_birth'   => 'date',
            'enrollment_date' => 'date',
        ];
    }

    /** Attributes the AuditObserver must never capture. */
    public function auditExcludedAttributes(): array
    {
        return ['updated_at', 'photo']; // photo path changes on every reupload — noisy
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class)->orderByDesc('enrolled_at');
    }

    public function academicDecisions(): HasMany
    {
        return $this->hasMany(AcademicDecision::class)->orderByDesc('finalized_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentGuardian::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot(['relationship', 'is_primary', 'can_pickup'])
            ->withTimestamps();
    }

    public function primaryParent(): BelongsTo
    {
        return $this->belongsTo(ParentGuardian::class, 'parent_id');
    }


    // ─── Computed attributes ────────────────────────────────────────

    public function fullName(): Attribute
    {
        return Attribute::get(
            fn (): string => trim("{$this->first_name} {$this->middle_name} {$this->last_name}")
        );
    }

    public function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $first  = Str::upper(Str::substr($this->first_name, 0, 1));
            $last   = Str::upper(Str::substr($this->last_name, 0, 1));
            return $first . $last;
        });
    }

    /** Age in completed years, or null when an official source omitted DOB. */
    public function age(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->date_of_birth
            ? (int) $this->date_of_birth->diffInYears(now())
            : null);
    }

    public function photoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->photo
                ? URL::temporarySignedRoute('public.media', now()->addMinutes(30), [
                    'directory' => 'students',
                    'filename' => basename($this->photo),
                ], false)
                : null
        );
    }

    // ─── Query scopes ───────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', StudentStatus::Active->value);
    }

    public function scopeArchived(Builder $q): Builder
    {
        return $q->where('status', StudentStatus::Archived->value);
    }

    /** Search by identity details or any current/historical class assignment. */
    public function scopeSearch(Builder $q, string $term): Builder
    {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($term)) . '%';
        return $q->where(function (Builder $q) use ($like): void {
            $q->where('first_name', 'ilike', $like)
                ->orWhere('last_name', 'ilike', $like)
                ->orWhere('middle_name', 'ilike', $like)
                ->orWhere('admission_number', 'ilike', $like)
                ->orWhere('official_matricule', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                // Users commonly search with only the visible first and last
                // names. An optional middle name must not break that match.
                ->orWhereRaw("concat_ws(' ', first_name, last_name) ILIKE ?", [$like])
                ->orWhereRaw("concat_ws(' ', first_name, middle_name, last_name) ILIKE ?", [$like])
                ->orWhereHas('schoolClass', function (Builder $class) use ($like): void {
                    $class->where('name', 'ilike', $like)
                        ->orWhere('grade_level', 'ilike', $like);
                })
                ->orWhereHas('enrollments.schoolClass', function (Builder $class) use ($like): void {
                    $class->where('name', 'ilike', $like)
                        ->orWhere('grade_level', 'ilike', $like);
                });
        });
    }
}
