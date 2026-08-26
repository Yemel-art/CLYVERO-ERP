<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToSchoolThroughRelation;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property ?string $user_id
 * @property string $activity
 * @property ?array $metadata
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property Carbon $created_at
 */
class ActivityLog extends Model
{
    use BelongsToSchoolThroughRelation, HasUuid;

    protected static function schoolTenantRelation(): string { return 'user'; }

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'activity', 'metadata',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
