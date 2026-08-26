<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The discrete actions recorded in the audit_logs table.
 *
 * Source: Security Blueprint §14 + Business Workflow Audit Trail.
 */
enum AuditAction: string
{
    case Created  = 'created';
    case Updated  = 'updated';
    case Deleted  = 'deleted';
    case Archived = 'archived';
    case Restored = 'restored';

    case LoggedIn  = 'logged_in';
    case LoggedOut = 'logged_out';
    case LoginFailed = 'login_failed';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordReset = 'password_reset';
    case PasswordChanged = 'password_changed';

    case RoleAssigned = 'role_assigned';
    case PermissionGranted = 'permission_granted';
    case PermissionRevoked = 'permission_revoked';

    case PaymentRecorded = 'payment_recorded';
    case PaymentRefunded = 'payment_refunded';

    case GradePublished = 'grade_published';
    case GradeModified  = 'grade_modified';

    case ReportCardGenerated = 'report_card_generated';
    case SettingsUpdated     = 'settings_updated';
}
