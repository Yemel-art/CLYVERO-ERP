<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\HasUuid;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * The authenticated User.
 *
 * @property string $id
 * @property string $role_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property ?string $phone
 * @property string $password
 * @property ?string $avatar
 * @property bool $is_active
 * @property ?Carbon $last_login_at
 * @property ?string $last_login_ip
 * @property bool $two_factor_enabled
 * @property int $failed_login_attempts
 * @property ?Carbon $locked_until
 * @property-read Role $role
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use BelongsToSchool;
    use HasFactory;
    use HasUuid;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'school_id',
        'role_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'avatar',
        'is_active',
        'two_factor_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'last_login_at'            => 'datetime',
            'locked_until'             => 'datetime',
            'two_factor_confirmed_at'  => 'datetime',
            'password'                 => 'hashed',
            'is_active'                => 'boolean',
            'two_factor_enabled'       => 'boolean',
            'failed_login_attempts'    => 'integer',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // ─── Accessors ──────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isAdministrator(): bool
    {
        return in_array($this->role?->name, [
            UserRole::Administrator->value,
            UserRole::SuperAdministrator->value,
        ], true);
    }

    public function isSuperAdministrator(): bool
    {
        return $this->role?->name === UserRole::SuperAdministrator->value;
    }

    public function isSecretary(): bool
    {
        return $this->role?->name === UserRole::Secretary->value;
    }

    public function isTeacher(): bool
    {
        return $this->role?->name === UserRole::Teacher->value;
    }

    public function isParent(): bool
    {
        return $this->role?->name === UserRole::Parent->value;
    }

    /**
     * Check whether the user (via their role) holds a given permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->role?->permissions()->where('name', $permission)->exists() ?? false;
    }

    public function roleEnum(): ?UserRole
    {
        return $this->role
            ? UserRole::tryFrom($this->role->name)
            : null;
    }
}
