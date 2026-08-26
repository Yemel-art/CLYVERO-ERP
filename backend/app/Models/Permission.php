<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $name          dot.notation e.g. "student.create"
 * @property string $module        e.g. "student"
 * @property string $action        e.g. "create"
 * @property string $display_name
 * @property ?string $description
 */
class Permission extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = ['name', 'module', 'action', 'display_name', 'description'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }
}
