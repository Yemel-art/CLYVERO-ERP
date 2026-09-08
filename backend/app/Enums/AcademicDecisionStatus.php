<?php

declare(strict_types=1);

namespace App\Enums;

enum AcademicDecisionStatus: string
{
    case Promoted = 'promoted';
    case Repeating = 'repeating';
    case Excluded = 'excluded';

    public function label(string $locale): string
    {
        return match ($locale) {
            'fr' => match ($this) {
                self::Promoted => 'Passe en classe supérieure',
                self::Repeating => 'Redouble',
                self::Excluded => 'Exclu',
            },
            default => match ($this) {
                self::Promoted => 'Promoted',
                self::Repeating => 'Repeating',
                self::Excluded => 'Excluded',
            },
        };
    }
}
