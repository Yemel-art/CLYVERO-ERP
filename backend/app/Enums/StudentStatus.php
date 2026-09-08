<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle status of a student record.
 *
 *   Active    — currently enrolled.
 *   Archived  — soft-deleted via secretary action (recoverable).
 *   Graduated — completed final year; preserved for transcripts.
 *   Withdrawn — left before completion; preserved for records.
 *
 * Source: Database Specification §7 + Business Workflow §3.
 */
enum StudentStatus: string
{
    case Active     = 'active';
    case Archived   = 'archived';
    case Graduated  = 'graduated';
    case Excluded   = 'excluded';
    case Withdrawn  = 'withdrawn';

    public function displayName(): string
    {
        return match ($this) {
            self::Active    => 'Active',
            self::Archived  => 'Archived',
            self::Graduated => 'Graduated',
            self::Excluded  => 'Excluded',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active    => 'success',
            self::Archived  => 'secondary',
            self::Graduated => 'info',
            self::Excluded  => 'danger',
            self::Withdrawn => 'warning',
        };
    }
}
