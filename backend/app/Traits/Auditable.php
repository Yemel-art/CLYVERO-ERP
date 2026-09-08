<?php

declare(strict_types=1);

namespace App\Traits;

use App\Observers\AuditObserver;

/**
 * Add to any Eloquent model that should be audited.
 *
 * The model declares a public `string $auditModule` property naming its
 * module (e.g. "student", "payment"); the observer reads that to set the
 * audit_logs.module column.
 *
 * Source: Security Blueprint §14 + Backend Blueprint §12 (Events) +
 * Rules.md #12 (use Observers — never sprinkle audit calls through Services).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    /**
     * Optional whitelist — when set, only these attributes are captured
     * in old_values/new_values (e.g. to avoid logging password hashes).
     *
     * @return array<int, string>
     */
    public function auditableAttributes(): array
    {
        return [];
    }

    /**
     * Attributes to NEVER include in audit logs.
     *
     * @return array<int, string>
     */
    public function auditExcludedAttributes(): array
    {
        return [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'updated_at',
        ];
    }
}
