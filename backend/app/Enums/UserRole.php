<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The four standard user roles in Clyvero ERP.
 *
 * Source: SRS §3 Target Users + Security Blueprint §3 User Roles.
 */
enum UserRole: string
{
    case SuperAdministrator = 'super_administrator';
    case Administrator = 'administrator';
    case Secretary     = 'secretary';
    case Teacher       = 'teacher';
    case Parent        = 'parent';

    public function displayName(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Platform Administrator',
            self::Administrator => 'Administrator',
            self::Secretary     => 'Secretary',
            self::Teacher       => 'Teacher',
            self::Parent        => 'Parent',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Creates and securely administers customer schools on the Clyvero platform.',
            self::Administrator => 'Full system access. Manages users, settings, finances, and academic configuration.',
            self::Secretary     => 'Registers students and teachers, manages tuition payments, prints report cards, manages attendance.',
            self::Teacher       => 'Views assigned classes, enters and modifies grades, records attendance.',
            self::Parent        => 'Views their children\'s grades, attendance, report cards, and tuition status.',
        };
    }

    /**
     * Default landing route after login for each role.
     */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::SuperAdministrator => '/platform/schools',
            self::Administrator => '/admin/dashboard',
            self::Secretary     => '/secretary/dashboard',
            self::Teacher       => '/teacher/dashboard',
            self::Parent        => '/parent/dashboard',
        };
    }

    /** @return array<int, self> */
    public static function staff(): array
    {
        return [self::Administrator, self::Secretary, self::Teacher];
    }
}
