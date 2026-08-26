<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Traits\Auditable;
use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Parent / Guardian record.
 *
 * Named ParentGuardian on the PHP side because "Parent" is a reserved
 * keyword. Maps to the `parents` table.
 *
 * @property string $id
 * @property ?string $user_id
 * @property string $first_name
 * @property string $last_name
 * @property Gender $gender
 * @property string $email
 * @property string $phone
 * @property bool $is_active
 */
class ParentGuardian extends Model
{
    use Auditable;
    use BelongsToSchool;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'parents';

    public string $auditModule = 'parent';

    protected $fillable = [
        'school_id', 'user_id',
        'first_name', 'last_name', 'middle_name', 'gender',
        'email', 'phone', 'alternate_phone',
        'address', 'city', 'country',
        'occupation', 'workplace', 'national_id',
        'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'gender'    => Gender::class,
            'is_active' => 'boolean',
        ];
    }

    public function auditExcludedAttributes(): array
    {
        return ['updated_at', 'national_id'];
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')
            ->withPivot(['relationship', 'is_primary', 'can_pickup'])
            ->withTimestamps();
    }

    // ─── Accessors ──────────────────────────────────────────────────

    public function fullName(): Attribute
    {
        return Attribute::get(
            fn (): string => trim("{$this->first_name} {$this->middle_name} {$this->last_name}")
        );
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
        return $q->where(function (Builder $q) use ($like): void {
            $q->where('first_name', 'ilike', $like)
                ->orWhere('last_name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhere('phone', 'ilike', $like);
        });
    }
}
