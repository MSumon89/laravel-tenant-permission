# Changelog

## 1.1.0 (2026-11-24)

### Added
- **Branches** (`PERMISSION_BRANCHES`, off by default), with or without tenancy,
  using the app's own branch table:
  - `BranchResolver` contract, `DefaultBranchResolver` (reads `users.branch_id`),
    and an optional `HasBranches` contract for the User model.
  - `branch_id` on role assignments and per-user overrides. `0` = every branch,
    which is the default for every write. `assignRole()`, `removeRole()`,
    `syncRoles()`, `givePermissionTo()`, `denyPermissionTo()`,
    `clearPermissionOverride()`, `syncPermissionOverrides()` and
    `getPermissionOverrides()` take `branchId:`.
  - Checks (`hasPermissionTo()`, `hasRole()`, `isTenantAdmin()`,
    `getAllPermissions()`, …) take `branchId:`, defaulting to the current branch.
    "All branches" (`0`) counts only every-branch assignments.
  - `resolve_branch` middleware (session-based, validated against the resolver),
    and a `POST branch/switch` route.
  - `BelongsToBranch` trait and `BranchScope` for branch-owned models, with
    `forBranch()` and `withoutBranchScope()`. Strict by default: an HTTP request
    with no branch context returns no rows.
  - `PermissionManager::setCurrentBranchId()`, `getCurrentBranchId()`,
    `getAccessibleBranchIds()`, `isAllBranches()`, `visibleBranchIds()`.
- Migration `add_branch_id_to_permission_pivots` (run `php artisan migrate`).

### Changed
- The same role can now be held more than once by a user, once per branch.
  Assignments are written with an insert instead of `syncWithoutDetaching()`.
- The built-in UI manages every-branch assignments. An `is_admin` role held
  in a single branch does not make someone a UI admin.
- `PermissionRegistrar::rememberEffectivePermissions()` takes an optional
  fourth `$branchId` argument, and cache keys include the branch.

## 1.0.0 (2026-11-23)

First public release. It is extracted from the private in-repo copy `smk/permission`;
the lists below are the differences from that copy. The namespace changed from
`Smk\Permission` to `BuildWithSmk\TenantPermission`.

### Added
- `tenancy.enabled` switch (`PERMISSION_TENANCY`). Off = plain single-app RBAC.
- `TenantResolver` contract, `DefaultTenantResolver`, and
  `PermissionManager::setCurrentTenantId()` for subdomain/URL-based tenancy.
- Optional management UI (`PERMISSION_UI`): roles, permission catalog, user
  roles + grant/deny overrides. Tenant-isolated, with no privilege escalation.
  Views are publishable (`permission-views`), and the layout/section are configurable.
- `syncRoles()`, `syncPermissionOverrides()`, `getPermissionOverrides()`,
  `getPermissionsViaRoles()`, `rolesForTenant()`, `getRoleNames()`, `hasAnyRole()`.
- `Role::syncPermissions()`, and variadic `givePermissionTo('a', 'b')`.
- `roles.description` column.
- Commands: `permission:cache-reset`, `permission:super-admin {user}`.
- Pest test suite (Testbench).

### Fixed
- Fresh installs never got the `is_admin` column: migrations now auto-load
  individually and skip only the ones the app has published.
- `forgetAll()` flushed the entire application cache. It now uses version keys.
- Changing a global role's permissions left tenant users' caches stale.
- `column_names.model_morph_key` was ignored by indexes and overrides.
- `models.role` / `models.permission` config was ignored.
- `hasRole()` / `isSuperAdmin()` stayed stale after `assignRole()` in the
  same request.
- Deleting a user left orphan rows in the polymorphic pivots.
- Guests hitting `permission:` routes got 403 instead of 401 / login redirect.

### Changed
- Requires PHP 8.2+ and Laravel 11+.
- Tenant admins (`is_admin`) now get every permission from
  `getAllPermissions()` / `hasPermissionTo()`, matching the Gate.
- `hasRole()` counts global roles in every tenant.
- Facade alias renamed `Permission` → `PermissionManager`.
- `Role::findByName()` / `findOrCreate()` tenant argument is optional.
