<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define all permissions per menu and action
        $permissions = [
            // Menu Permissions
            'menu.dashboard',
            'menu.offers',
            'menu.work-orders',
            'menu.reports',
            'menu.imports',
            'menu.audit-logs',
            'menu.master-users',
            'menu.master-data',

            // Action Permissions
            'users.manage',
            'work-orders.assign-pic',
            'work-orders.change-status',
            'work-orders.edit-sla',
            'work-orders.manage-assets',
            'work-orders.survey',
            'work-orders.review',

            // Automatic offer document permissions
            'offers.documents.view',
            'offers.documents.manage',
            'offers.documents.generate-draft',
            'offers.documents.generate-print-ready',
            'offers.document-masters.view',
            'offers.document-masters.manage',
            'offers.document-masters.approve',
            'offers.cross-branch',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // 2. Create Roles and Assign Permissions

        // Sysadmin manages the catalog but cannot approve document masters.
        $sysadminRole = Role::findOrCreate('sysadmin', 'web');
        $sysadminRole->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->where('name', '!=', 'offers.document-masters.approve')
                ->get(),
        );

        // Supervisor: Access to operational menus, reports, master-data, and management actions
        $supervisorRole = Role::findOrCreate('supervisor', 'web');
        $supervisorRole->syncPermissions([
            'menu.dashboard',
            'menu.offers',
            'menu.work-orders',
            'menu.reports',
            'menu.master-data',
            'work-orders.assign-pic',
            'work-orders.change-status',
            'work-orders.edit-sla',
            'work-orders.manage-assets',
            'work-orders.survey',
            'work-orders.review',
            'offers.documents.view',
            'offers.documents.manage',
            'offers.documents.generate-draft',
            'offers.documents.generate-print-ready',
            'offers.document-masters.view',
            'offers.document-masters.approve',
        ]);

        // Admin: Access to dashboard, offers, work orders, change status
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->syncPermissions([
            'menu.dashboard',
            'menu.offers',
            'menu.work-orders',
            'work-orders.change-status',
            'work-orders.manage-assets',
            'offers.documents.view',
            'offers.documents.manage',
            'offers.documents.generate-draft',
        ]);

        // Reviewer: Access to dashboard, work-orders, change status, review
        $reviewerRole = Role::findOrCreate('reviewer', 'web');
        $reviewerRole->syncPermissions([
            'menu.dashboard',
            'menu.work-orders',
            'work-orders.change-status',
            'work-orders.review',
        ]);

        // Surveyor: Access to dashboard, work-orders, survey
        $surveyorRole = Role::findOrCreate('surveyor', 'web');
        $surveyorRole->syncPermissions([
            'menu.dashboard',
            'menu.work-orders',
            'work-orders.survey',
        ]);

        // 3. Assign roles to existing users based on their string 'role' column
        foreach (User::all() as $user) {
            if ($user->role && in_array($user->role, ['sysadmin', 'supervisor', 'admin', 'reviewer', 'surveyor'])) {
                $user->syncRoles([$user->role]);
            } else {
                $user->syncRoles(['admin']);
            }
        }
    }
}
