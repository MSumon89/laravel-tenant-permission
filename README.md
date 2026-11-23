# buildwithsmk/laravel-tenant-permission

Roles and permissions for Laravel 11, 12 and 13 that work the same way in two kinds of app:

| | Single app (default) | Multi-tenant (`PERMISSION_TENANCY=true`) |
|---|---|---|
| Roles | One shared set | A separate set per tenant (shop, clinic, hotel, account…) plus optional global roles |
| Users | Any number of roles | Any number of roles within their tenant |
| Super Admin | Passes every check | Passes every check, in every tenant |
| "Full access" role (`is_admin`) | Holds every permission | Holds every permission **in its own tenant** |
| Per-user overrides | Grant / deny single permissions | Same, per tenant |

**Effective permissions = role permissions + direct grants − direct denies.** A deny always wins.

Also included:

- `@can`, `$user->can()`, `$this->authorize()` work with permission names out of the box.
- Route middleware: `permission:` and `tenant_role:`.
- Per-user caching that invalidates itself. Your app cache is never flushed.
- **Optional** management UI (roles, permission catalog, user access) with publishable views.

---

## 1. Install

```bash
composer require buildwithsmk/laravel-tenant-permission
php artisan permission:install          # publishes config/permission.php
```

Before migrating, open `config/permission.php`:

- **Multi-tenant app?** Set `PERMISSION_TENANCY=true` in `.env`.
- Change `table_names` now if you need to (e.g. `spatie/laravel-permission` is also installed, because both use `roles`, `permissions`, …).

```bash
php artisan migrate
```

The migrations load automatically from the package, so you don't need to publish them. They are safe to run on a database that already has the tables. To keep editable copies in your app, run `php artisan permission:install --migrations`.

Add the trait to your User model:

```php
use Smk\Permission\Traits\HasRolesAndPermissions;

class User extends Authenticatable
{
    use HasRolesAndPermissions;
}
```

Seed the basics and make yourself Super Admin:

```bash
php artisan db:seed --class="Smk\Permission\Database\Seeders\PermissionSeeder"
php artisan permission:super-admin you@example.com
```

---

## 2. Single-app usage (no tenancy)

```php
use Smk\Permission\Models\Permission;
use Smk\Permission\Models\Role;

// Seeder: your permission catalog ("<module>.<action>")
foreach (['posts', 'users'] as $module) {
    foreach (['view', 'create', 'edit', 'delete'] as $action) {
        Permission::findOrCreate("{$module}.{$action}");
    }
}

Role::findOrCreate('Editor')->givePermissionTo('posts.view', 'posts.edit');
Role::findOrCreate('Admin')->update(['is_admin' => true]); // every permission

$user->assignRole('Editor');
$user->hasPermissionTo('posts.edit');   // true
$user->can('posts.edit');               // true (Gate)
```

That's all. With tenancy off, any `tenant_id` column on your users is ignored and everything lives in the global scope.

---

## 3. Multi-tenant usage

Set `PERMISSION_TENANCY=true`. The package then needs to know **which tenant a check runs in**. It looks in this order:

1. A tenant pinned for the request: `PermissionManager::setCurrentTenantId($id)`
2. The user's own tenant, from the resolver (default: `$user->getTenantId()` or the `tenant_id` column)
3. Otherwise the global scope (platform staff, Super Admin)

Pick the setup that matches your app:

**A. Each user belongs to one tenant, stored as a column (most common).**
If the column is `tenant_id`, there's nothing to do. For another name, either set `tenancy.tenant_column`, or implement the contract:

```php
use Smk\Permission\Contracts\BelongsToTenant;

class User extends Authenticatable implements BelongsToTenant
{
    use HasRolesAndPermissions;

    public function getTenantId(): ?int
    {
        return $this->shop_id;
    }
}
```

**B. Users can work in several tenants (tenant comes from subdomain, URL or session).**
Pin the current tenant in your own middleware, after you identify it:

```php
use Smk\Permission\Facades\PermissionManager;

public function handle(Request $request, Closure $next)
{
    $tenant = Tenant::whereDomain($request->getHost())->firstOrFail();
    PermissionManager::setCurrentTenantId($tenant->id);

    return $next($request);
}
```

Run this middleware **before** any `permission:` middleware or `@can` checks. The value resets automatically after each request and job, including under Octane.

**C. You already use a tenancy package.**
Write a resolver and point `tenancy.resolver` at it:

```php
use Smk\Permission\Contracts\TenantResolver;

class CurrentTenantResolver implements TenantResolver
{
    public function resolve(Model $model): ?int
    {
        return tenant()?->getKey();
    }
}
```

Creating tenant roles, e.g. when a new tenant signs up:

```php
$owner = Role::findOrCreate('Owner', $tenant->id);
$owner->update(['is_admin' => true, 'is_system' => true]); // full access, can't be deleted

Role::findOrCreate('Staff', $tenant->id)->givePermissionTo('rooms.view', 'bookings.create');

$user->assignRole('Owner');                 // resolved in the user's tenant
$user->assignRole('Staff', $otherTenantId); // or an explicit tenant
```

Global roles (`tenant_id = 0`) apply in every tenant. The permission catalog is always global and shared by all tenants.

> Never use `0` as a real tenant id. It is the global scope (`global_tenant_id`).

---

## 4. API reference

**On the user** (every `$tenantId` is optional and defaults to the resolved scope):

| Method | |
|---|---|
| `assignRole($role, $tenantId)` / `removeRole(...)` | `$role` = name or `Role` |
| `syncRoles([...], $tenantId)` | names, ids or models. Only that tenant's roles are replaced |
| `hasRole('Manager')`, `hasRole(['A', 'B'])`, `hasAnyRole([...])` | global roles count everywhere |
| `getRoleNames()`, `rolesForTenant()` | |
| `givePermissionTo('x')` / `denyPermissionTo('x')` / `clearPermissionOverride('x')` | direct overrides |
| `syncPermissionOverrides(['x' => 'grant', 'y' => 'deny'])` | replace all overrides in a tenant |
| `hasPermissionTo('x')`, `hasAnyPermission([...])`, `hasAllPermissions([...])` | |
| `getAllPermissions()`, `getPermissionsViaRoles()`, `getPermissionOverrides()` | |
| `isSuperAdmin()`, `isTenantAdmin()` | |

**On a role:** `givePermissionTo('a', 'b')`, `revokePermissionTo(...)`, `syncPermissions([...])`, `hasPermissionTo('a')`, `Role::findByName($name, $tenantId)`, `Role::findOrCreate($name, $tenantId)`.

**Routes:**

```php
Route::put('/posts/{post}', ...)->middleware('permission:posts.edit');
Route::get('/reports', ...)->middleware('permission:reports.view|reports.export'); // any of
Route::get('/till', ...)->middleware('tenant_role:Manager|Cashier');
```

Guests get a 401 (redirect to login). Users without access get a 403 (`Smk\Permission\Exceptions\UnauthorizedException`).

**Blade:** `@can('posts.edit') … @endcan`. No custom directive is needed.

**Cache:** handled for you. To reset it by hand, run `php artisan permission:cache-reset`.

---

## 5. The built-in UI (optional)

```dotenv
PERMISSION_UI=true
```

Then open `/access-control`. You get three screens:

- **Roles**: create, edit and delete roles, with a permission picker grouped by module.
- **Users**: assign roles, and set Inherit / Grant / Deny per permission. Shows the effective result.
- **Permissions**: the catalog.

**Who can do what** (enforced on the server, not just hidden in the page):

| Actor | Can |
|---|---|
| Super Admin | Everything, including the permission catalog and the Super Admin role |
| Admin (`is_admin` role in the scope) | Everything in their scope. Catalog edits only in single-app mode |
| User with `access-control.manage` | Manage roles and users, but only hand out permissions **they hold themselves** |
| Anyone else | 403 |

With tenancy on, each person only sees their own tenant's roles and users (other tenants' records return 404), and only a Super Admin can change the global catalog. Nobody can remove their own admin role, so you can't lock yourself out.

**Customising the look.** There are three levels:

1. **Use your own layout.** Point the config at a layout that `@yield`s a section:
   ```php
   'ui' => ['layout' => 'layouts.admin', 'section' => 'content'],
   ```
   The pages bring their own scoped CSS (`.pm-*` classes, light and dark), so they look right in any layout. You don't need Tailwind or a build step.
2. **Redesign the pages.** Publish the views and edit them freely:
   ```bash
   php artisan vendor:publish --tag=permission-views
   ```
   They land in `resources/views/vendor/permission/` and override the package's copies. Routes, controllers, validation and the security rules above stay in the package, so your redesigned forms keep working. Keep the input names (`permissions[]`, `roles[]`, `overrides[<name>]`).
3. **Build your own screens.** Leave the UI off and call the API in section 4 from your own controllers.

Other UI options: `prefix`, `route_name_prefix`, `middleware`, `per_page`, `user_display_column`, `user_search_columns`.
To scope the user list yourself (multi-tenant), add `scopeInPermissionTenant(Builder $query, int $tenantId)` to your User model. Otherwise `tenancy.tenant_column` is used.

---

## 6. Upgrading from the old in-repo copy

If your app used the earlier copy at `packages/smk/permission`:

1. **Multi-tenant apps must set `PERMISSION_TENANCY=true`.** Tenancy is now opt-in. Without it, every check runs in the global scope.
2. Remove the old `path` repository and `smk/permission` from your composer.json, run `composer require buildwithsmk/laravel-tenant-permission`, then `php artisan migrate`. The PHP namespace (`Smk\Permission`) is unchanged, so no code edits are needed. Missing columns (`roles.description`, `roles.is_admin`) are added, and existing tables are left alone.
3. Behaviour changes to know about:
   - `getAllPermissions()` and `hasPermissionTo()` now return *all* permissions for tenant admins, matching what the Gate and middleware already did.
   - `hasRole()` now also counts global roles.
   - `PermissionRegistrar::forgetAll()` no longer flushes the whole app cache.
   - The facade alias is `PermissionManager` (was `Permission`).

## Requirements

PHP 8.2+, Laravel 11, 12 or 13.

## License

MIT
