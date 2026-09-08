<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the full permission catalog (one row per module.action) and
 * assigns the default permission set to each role.
 *
 * Administrators get all permissions. Other roles get a curated subset
 * matching the SRS §3 (Target Users) responsibilities.
 *
 * Future phases will add new permissions here as new modules are built.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Module → list of actions.
     *
     * @var array<string, array<int, string>>
     */
    private array $catalog = [
        // Phase 1
        'user'     => ['view', 'create', 'edit', 'delete', 'assign_role', 'reset_password'],
        'role'     => ['view', 'create', 'edit', 'delete'],
        'audit'    => ['view'],
        'activity' => ['view'],
        'school'   => ['view', 'edit'],

        // People
        'student'     => ['view', 'create', 'edit', 'delete', 'archive', 'restore', 'export'],
        'teacher'     => ['view', 'create', 'edit', 'delete', 'export'],
        'parent'      => ['view', 'create', 'edit', 'delete'],

        // Academic structure
        'class'         => ['view', 'create', 'edit', 'delete', 'archive', 'restore'],
        'subject'       => ['view', 'create', 'edit', 'delete', 'archive', 'restore'],
        'academic_year' => ['view', 'create', 'edit', 'archive', 'delete', 'activate'],
        'term'          => ['view', 'create', 'edit', 'delete', 'activate', 'close'],

        // Operational
        'attendance'  => ['view', 'create', 'edit', 'record'],
        'grade'       => ['view', 'create', 'edit', 'delete', 'record', 'publish'],
        'timetable'   => ['view', 'create', 'edit', 'delete'],

        // Finance — split into fee/invoice/payment for fine-grained gating
        'finance'     => ['view', 'export'],
        'fee'         => ['view', 'create', 'edit', 'delete'],
        'invoice'     => ['view', 'create', 'edit', 'cancel'],
        'payment'     => ['view', 'create', 'void'],
        'cafeteria'   => ['view', 'create', 'edit'],

        // Reporting & system
        'report_card' => ['view', 'generate', 'download'],
        'dashboard'   => ['view_admin', 'view_secretary', 'view_teacher', 'view_parent'],
        'settings'    => ['view', 'edit'],
        'notification' => ['view', 'send'],
    ];

    public function run(): void
    {
        $created = $this->seedCatalog();
        $this->assignDefaults($created);
    }

    /**
     * @return array<string, string>  permission_name → permission_id
     */
    private function seedCatalog(): array
    {
        $map = [];
        foreach ($this->catalog as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                $permission = Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'module'       => $module,
                        'action'       => $action,
                        'display_name' => ucfirst(str_replace('_', ' ', $action)) . ' ' . str_replace('_', ' ', $module),
                        'description'  => null,
                    ],
                );
                $map[$name] = $permission->id;
            }
        }
        return $map;
    }

    /**
     * @param  array<string, string>  $allPermissions
     */
    private function assignDefaults(array $allPermissions): void
    {
        $defaults = [
            UserRole::SuperAdministrator->value => array_keys($allPermissions),
            UserRole::Administrator->value => array_keys($allPermissions),

            UserRole::Secretary->value => [
                'dashboard.view_secretary',
                'student.view', 'student.create', 'student.edit', 'student.archive', 'student.restore', 'student.export',
                'parent.view', 'parent.create', 'parent.edit',
                'teacher.view',
                'class.view', 'class.create', 'class.edit',
                'subject.view', 'subject.create', 'subject.edit',
                'attendance.view', 'attendance.create', 'attendance.edit', 'attendance.record',
                'finance.view', 'finance.export',
                'fee.view',
                'invoice.view', 'invoice.create', 'invoice.edit',
                'payment.view', 'payment.create',
                'cafeteria.view', 'cafeteria.create',
                'report_card.view', 'report_card.generate', 'report_card.download',
                'academic_year.view',
                'term.view',
            ],

            UserRole::Teacher->value => [
                'dashboard.view_teacher',
                'student.view',
                'class.view',
                'subject.view',
                'attendance.view', 'attendance.create', 'attendance.edit', 'attendance.record',
                'grade.view', 'grade.create', 'grade.edit', 'grade.record', 'grade.publish',
                'timetable.view',
                'academic_year.view',
                'term.view',
                'report_card.view',
            ],

            UserRole::Parent->value => [
                'dashboard.view_parent',
                'student.view',          // scoped to own children by Policy
                'grade.view',            // scoped to own children by Policy
                'attendance.view',       // scoped to own children by Policy
                'report_card.view', 'report_card.download',
                'finance.view',          // scoped to own children by Policy
                'invoice.view',
                'payment.view',
                'cafeteria.view',
            ],
        ];

        foreach ($defaults as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            if ($role === null) {
                continue;
            }

            $ids = array_values(array_intersect_key(
                $allPermissions,
                array_flip($permissionNames),
            ));

            $role->permissions()->sync($ids);
        }
    }
}
