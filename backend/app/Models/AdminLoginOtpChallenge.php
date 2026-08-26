<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminLoginOtpChallenge extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id', 'code_hash', 'attempts', 'remember_me',
        'expires_at', 'consumed_at', 'requested_ip',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'remember_me' => 'boolean',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
