<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent  = 'absent';
    case Late    = 'late';
    case Excused = 'excused';

    public function displayName(): string
    {
        return ucfirst($this->value);
    }
}
