<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two secondary education systems supported by the platform.
 *
 *   SecondaryGeneral   — Enseignement secondaire général
 *   SecondaryTechnical — Enseignement secondaire technique
 */
enum StudentCycle: string
{
    case SecondaryGeneral   = 'secondary_general';
    case SecondaryTechnical = 'secondary_technical';

    public function label(): string
    {
        return match ($this) {
            self::SecondaryGeneral   => 'Secondary General',
            self::SecondaryTechnical => 'Secondary Technical',
        };
    }

    public function labelFr(): string
    {
        return match ($this) {
            self::SecondaryGeneral   => 'Secondaire General',
            self::SecondaryTechnical => 'Secondaire Technique',
        };
    }

    /** Whether this education system requires a speciality (spécialité). */
    public function requiresSpeciality(): bool
    {
        return $this === self::SecondaryTechnical;
    }
}
