# Changelog

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
