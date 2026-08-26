<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToSchoolThroughRelation;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAvailability extends Model
{
    use BelongsToSchoolThroughRelation, HasUuid;

    protected static function schoolTenantRelation(): string { return 'teacher'; }

    protected $fillable = ['teacher_id', 'day_of_week', 'period_index', 'is_available'];

    protected function casts(): array
    {
        return ['period_index' => 'integer', 'is_available' => 'boolean'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
