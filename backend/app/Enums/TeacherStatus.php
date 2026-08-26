<?php

declare(strict_types=1);

namespace App\Enums;

enum TeacherStatus: string
{
    case Active     = 'active';
    case OnLeave    = 'on_leave';
    case Archived   = 'archived';
    case Terminated = 'terminated';

    public function displayName(): string
    {
        return match ($this) {
            self::Active     => 'Active',
            self::OnLeave    => 'On leave',
            self::Archived   => 'Archived',
            self::Terminated => 'Terminated',
        };
    }
}
