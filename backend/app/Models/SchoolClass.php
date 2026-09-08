<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToSchoolThroughAcademicYear;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use Auditable, BelongsToSchoolThroughAcademicYear, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'school_classes';
    public string $auditModule = 'class';

    public const CYCLES = [
        'first_cycle' => 'First Cycle (Premier cycle)',
        'second_cycle' => 'Second Cycle (Second cycle)',
    ];

    public const EDUCATION_SYSTEMS = [
        'secondary_general' => 'Secondary General Education',
        'secondary_technical' => 'Secondary Technical Education',
    ];

    public const LEVELS_BY_SYSTEM = [
        'secondary_general' => [
            'en' => [
                'first_cycle' => ['Form 1', 'Form 2', 'Form 3', 'Form 4', 'Form 5'],
                'second_cycle' => ['Lower Sixth', 'Upper Sixth'],
            ],
            'fr' => [
                'first_cycle' => ['6ème', '5ème', '4ème', '3ème'],
                'second_cycle' => ['2nde', '1ère', 'Terminale'],
            ],
        ],
        'secondary_technical' => [
            'en' => [
                'first_cycle' => ['Form 1', 'Form 2', 'Form 3', 'Form 4', 'Form 5'],
                'second_cycle' => ['Lower Sixth', 'Upper Sixth'],
            ],
            'fr' => [
                'first_cycle' => ['Première année', 'Deuxième année', 'Troisième année', 'Quatrième année', '2nde'],
                'second_cycle' => ['1ère', 'Terminale'],
            ],
        ],
    ];

    protected $fillable = [
        'academic_year_id', 'education_system', 'form_master_id', 'name', 'grade_level',
        'next_grade_level', 'is_terminal', 'speciality', 'cycle', 'language', 'capacity',
        'description', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'is_active' => 'boolean', 'is_terminal' => 'boolean'];
    }

    /** @return array<int, string> */
    public static function levelsForCycle(?string $cycle, string $educationSystem = 'secondary_general', string $language = 'en'): array
    {
        return self::LEVELS_BY_SYSTEM[$educationSystem][$language][$cycle] ?? [];
    }

    public static function nextLevelFor(?string $gradeLevel): ?string
    {
        if ($gradeLevel === null) {
            return null;
        }

        $normalized = mb_strtolower(trim(strtok($gradeLevel, '(')));
        $progression = [
            'form 1' => 'Form 2', 'form 2' => 'Form 3', 'form 3' => 'Form 4',
            'form 4' => 'Form 5', 'form 5' => 'Lower Sixth',
            'lower sixth' => 'Upper Sixth', 'upper sixth' => null,
            '6ème' => '5ème', '5ème' => '4ème', '4ème' => '3ème', '3ème' => '2nde',
            '2nde' => '1ère', '1ère' => 'Terminale', 'terminale' => null,
            'première année' => 'Deuxième année', 'deuxième année' => 'Troisième année',
            'troisième année' => 'Quatrième année', 'quatrième année' => '2nde',
        ];

        return $progression[$normalized] ?? null;
    }

    public static function isTerminalLevel(?string $gradeLevel): bool
    {
        return in_array(mb_strtolower(trim(strtok((string) $gradeLevel, '('))), ['upper sixth', 'terminale'], true);
    }

    public static function cycleForLevel(string $gradeLevel, string $educationSystem, string $language): string
    {
        foreach (self::LEVELS_BY_SYSTEM[$educationSystem][$language] ?? [] as $cycle => $levels) {
            if (in_array($gradeLevel, $levels, true)) {
                return $cycle;
            }
        }

        return 'first_cycle';
    }

    /**
     * A compact, single-language label suitable for identity cards and official lists.
     * It deliberately avoids the bilingual class name stored for administrative screens.
     */
    public function identityLabel(): string
    {
        $level = trim((string) $this->grade_level);
        $speciality = trim((string) $this->speciality);

        if ($speciality === '') {
            return $level !== '' ? $level : trim((string) $this->name);
        }

        $definition = config("student.specialities.{$speciality}")
            ?? config("student.general_streams.{$speciality}");
        $nameKey = $this->language === 'en' ? 'name_en' : 'name';
        $specialityName = is_array($definition)
            ? (string) ($definition[$nameKey] ?? $definition['name'] ?? $speciality)
            : $speciality;

        return trim($level.' '.$specialityName);
    }

    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function formMaster(): BelongsTo { return $this->belongsTo(Teacher::class, 'form_master_id'); }
    public function students(): HasMany { return $this->hasMany(Student::class, 'class_id'); }
    public function enrollments(): HasMany { return $this->hasMany(StudentEnrollment::class, 'class_id'); }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject', 'class_id', 'subject_id')
            ->withPivot(['teacher_id', 'coefficient', 'weekly_frequency'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
