<?php

namespace Smk\Permission\Database\Seeders;

use Illuminate\Database\Seeder;
use Smk\Permission\PermissionRegistrar;

/**
 * Seeds what every app needs, whatever its domain:
 *
 *  - the global Super Admin role (protected from deletion);
 *  - the `access-control.manage` permission that opens the built-in UI.
 *
 * Your own permission catalog and roles are app-specific, so put them in
 * your own seeder. Example:
 *
 *     foreach (['posts', 'users'] as $module) {
 *         foreach (['view', 'create', 'edit', 'delete'] as $action) {
 *             Permission::findOrCreate("{$module}.{$action}");
 *         }
 *     }
 *
 *     // Single app: roles live in the global scope.
 *     Role::findOrCreate('Editor')->givePermissionTo('posts.view', 'posts.edit');
 *
 *     // Multi-tenant: one set of roles per tenant.
 *     Role::findOrCreate('Owner', $tenant->id)->update(['is_admin' => true, 'is_system' => true]);
 *     Role::findOrCreate('Staff', $tenant->id)->givePermissionTo('posts.view');
 */
class PermissionSeeder extends Seeder
{
    public function run(PermissionRegistrar $registrar): void
    {
        $roleClass = $registrar->roleClass();
        $permissionClass = $registrar->permissionClass();

        $superAdmin = $roleClass::findOrCreate($registrar->superAdminRoleName(), $registrar->globalTenantId());
        $superAdmin->forceFill(['is_system' => true])->save();

        $permissionClass::findOrCreate('access-control.manage', null, [
            'display_name' => 'Manage roles & permissions',
            'module' => 'access-control',
            'description' => 'Open the access-control screens and manage roles, permissions and user access.',
        ]);
    }
}
