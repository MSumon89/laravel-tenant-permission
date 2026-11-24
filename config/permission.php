<?php

use BuildWithSmk\TenantPermission\Branches\DefaultBranchResolver;
use BuildWithSmk\TenantPermission\Models\Permission;
use BuildWithSmk\TenantPermission\Models\Role;
use BuildWithSmk\TenantPermission\Tenancy\DefaultTenantResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | Disabled (default): plain single-app RBAC. Every role and every check
    | uses the global scope, and any tenant_id on your users is ignored.
    |
    | Enabled: roles, role assignments and per-user overrides are scoped to a
    | tenant (shop, clinic, hotel, account, ...). The tenant for a check is
    | resolved in this order:
    |
    |   1. PermissionRegistrar::setCurrentTenantId() / PermissionManager::setCurrentTenantId(),
    |      usually called from your own "current tenant" middleware;
    |   2. the resolver below, which by default reads $user->getTenantId()
    |      (BelongsToTenant contract) or the `tenant_column` attribute;
    |   3. the global scope (global_tenant_id), e.g. for platform staff.
    |
    */
    'tenancy' => [
        'enabled' => (bool) env('PERMISSION_TENANCY', false),

        // Column on the user model holding the tenant id. Used by the default
        // resolver and by the UI to list only the current tenant's users.
        // Set to null if users are not stored with a tenant column (e.g. a
        // database-per-tenant setup) and the UI should list every user.
        'tenant_column' => 'tenant_id',

        // Any class implementing BuildWithSmk\TenantPermission\Contracts\TenantResolver.
        'resolver' => DefaultTenantResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Branches (optional, works with or without tenancy)
    |--------------------------------------------------------------------------
    |
    | For apps whose data belongs to branches (offices, clinics, outlets):
    | HQ users can switch between branches or see all of them at once, and
    | branch users only ever see their own branch. Uses YOUR branch table;
    | the package only stores branch ids.
    |
    | - Role assignments and overrides get a branch_id. 0 = every branch,
    |   which is also what every assignment made without a branch gets.
    | - A check in branch X counts the user's branch-0 roles + branch-X roles.
    |   In "All branches" mode only branch-0 roles count.
    | - The `resolve_branch` middleware picks the current branch per request
    |   (session, validated against the resolver), and the BelongsToBranch
    |   trait filters your models by it.
    |
    | Disabled (default): branch_id is never read. Nothing changes.
    |
    */
    'branches' => [
        'enabled' => (bool) env('PERMISSION_BRANCHES', false),

        // Any class implementing BuildWithSmk\TenantPermission\Contracts\BranchResolver.
        'resolver' => DefaultBranchResolver::class,

        // Home-branch column on the user model, read by the default resolver
        // (unless the user implements HasBranches).
        'user_column' => 'branch_id',

        // Branch column on your data tables, used by the BelongsToBranch
        // trait. A model can override it with a $branchColumn property.
        'column' => 'branch_id',

        // Session key holding the selected branch (0 = "All branches").
        'session_key' => 'permission_branch_id',

        // What a multi-branch (HQ) user sees before choosing a branch:
        // false = their home branch, true = "All branches".
        'default_all' => false,

        // In an HTTP request with no branch context (the resolve_branch
        // middleware did not run), BelongsToBranch models return no rows.
        // Console commands and queue jobs are never filtered without a
        // context: set one there with PermissionManager::setCurrentBranchId().
        'strict' => true,

        // POST <uri> with `branch_id` (0 = all) switches the branch.
        'switch_route' => [
            'enabled' => true,
            'uri' => 'branch/switch',
            'name' => 'permission.branch.switch',
            'middleware' => ['web', 'auth'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global scope marker
    |--------------------------------------------------------------------------
    |
    | tenant_id = 0 means "global / platform". Never use 0 as a real tenant id.
    | Global roles apply in every tenant.
    |
    */
    'global_tenant_id' => 0,

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | Name of the global role that passes every check (Gate, middleware,
    | hasPermissionTo). Create it with the seeder or `permission:super-admin`.
    |
    */
    'super_admin_role' => 'Super Admin',

    'default_guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | You may swap in your own models as long as they extend the package's.
    | `user` = null means config('auth.providers.users.model').
    |
    */
    'models' => [
        'role' => Role::class,
        'permission' => Permission::class,
        'user' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table and column names
    |--------------------------------------------------------------------------
    |
    | Must be set BEFORE running the migrations. The defaults are the same as
    | spatie/laravel-permission's, so if you need both packages in one app,
    | prefix these (e.g. 'smk_roles').
    |
    */
    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'role_has_permissions' => 'role_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'model_has_permissions' => 'model_has_permissions',
    ],

    'column_names' => [
        // Column in model_has_roles / model_has_permissions holding the user id.
        'model_morph_key' => 'model_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel Gate integration
    |--------------------------------------------------------------------------
    |
    | When true, @can / $user->can() / $this->authorize() resolve permission
    | names through this package, and Super Admin / tenant-admin users pass
    | every ability.
    |
    */
    'register_gate' => true,

    /*
    |--------------------------------------------------------------------------
    | Route middleware aliases
    |--------------------------------------------------------------------------
    */
    'middleware_aliases' => [
        'permission' => 'permission',   // ->middleware('permission:posts.edit|posts.delete')
        'role' => 'tenant_role',        // ->middleware('tenant_role:Manager|Cashier')
        'branch' => 'resolve_branch',   // picks the current branch; run it before the two above
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Each user's effective permission list is cached per tenant. The package
    | invalidates it itself through version keys, so only its own entries are
    | dropped. Your app's cache is never flushed.
    |
    */
    'cache' => [
        'enabled' => true,
        'store' => null, // null = default store
        'ttl_seconds' => 3600,
        'key_prefix' => 'smk_permission',
    ],

    /*
    |--------------------------------------------------------------------------
    | Built-in management UI (optional)
    |--------------------------------------------------------------------------
    |
    | Screens for roles, the permission catalog and per-user access. Off by
    | default. Turn it on with PERMISSION_UI=true.
    |
    | Who can open it: anyone passing `middleware`. By default that means a
    | Super Admin, a tenant admin (is_admin role), or a user with the
    | `access-control.manage` permission. Non-admin managers can only grant
    | permissions they hold themselves.
    |
    */
    'ui' => [
        'enabled' => (bool) env('PERMISSION_UI', false),
        'prefix' => 'access-control',
        'route_name_prefix' => 'permission.ui.',
        'middleware' => ['web', 'auth', 'permission:access-control.manage'],

        // Blade layout the pages extend and the section they fill. Point
        // these at your own layout to embed the screens in your admin panel.
        'layout' => 'permission::layouts.app',
        'section' => 'content',

        'per_page' => 20,
        'user_display_column' => 'name',
        'user_search_columns' => ['name', 'email'],
    ],
];
