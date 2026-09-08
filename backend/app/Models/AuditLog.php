<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToSchoolThroughRelation;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Audit log entry — immutable from the application layer.
 *
 * Updates and deletes are blocked in `booted()`. Only `INSERT` is allowed.
 *
 * @property string $id
 * @property ?string $user_id
 * @property string $module
 * @property string $action
 * @property ?string $auditable_type
 * @property ?string $auditable_id
 * @property ?array $old_values
 * @property ?array $new_values
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    use BelongsToSchoolThroughRelation, HasUuid;

    protected static function schoolTenantRelation(): string { return 'user'; }

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'module', 'action',
        'auditable_type', 'auditable_id',
        'old_values', 'new_values',
        'ip_address', 'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Hard block updates and deletes from the application layer.
        static::updating(fn () => throw new \LogicException('Audit logs are immutable.'));
        static::deleting(fn () => throw new \LogicException('Audit logs cannot be deleted from the application.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
