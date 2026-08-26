<?php

declare(strict_types=1);

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Repeating = 'repeating';
    case Excluded = 'excluded';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';
}
