# Developer notes

Start here if you maintain this package. For *using* it, read the README.

## Layout

```
config/permission.php           every option, documented inline
database/migrations/            idempotent (hasTable / hasColumn guards)
database/seeders/               Super Admin role + access-control.manage
routes/web.php                  UI routes (only loaded when ui.enabled)
resources/views/                UI pages. partials/assets holds all CSS/JS
src/
  PermissionServiceProvider.php config, migrations, middleware, Gate, UI, commands
  PermissionRegistrar.php       THE core service: tenant scope, model classes, cache
  Traits/HasRolesAndPermissions everything a user can do / be asked
  Models/Role, Permission
  Contracts/                    BelongsToTenant, TenantResolver
  Tenancy/DefaultTenantResolver
  Middleware/                   permission:, tenant_role:
  Http/Controllers/             UI. Base Controller holds the access rules
  Http/UiHelper.php             $pm in views (route names, layout, scope label)
  Console/                      install, cache-reset, super-admin
tests/
  Feature/Core                  engine tests (UI off)
  Feature/Ui                    UI tests (UiTestCase turns the UI on at boot)
  Fixtures/                     User models + users table
```

## How a check is resolved

1. `$user->hasPermissionTo('x')` → `getAllPermissions($tenantId)`
2. `$tenantId` comes from `PermissionRegistrar::tenantIdFor($user)`:
   tenancy off → global (0). Otherwise pinned tenant → resolver → global.
3. Super Admin or `is_admin` role in that tenant → every catalog name.
4. Otherwise (cached): permissions of the user's roles in that tenant + global
   roles, plus grants, minus denies.

## Cache rules (don't break these)

- Keys contain a **global version** and a **per-user version**.
  `forgetAll()` bumps the global one, `forgetFor($user)` bumps the user's.
  Nothing is ever deleted by pattern, and `Cache::flush()` is never called.
- Anything that changes a **role's** permissions, deletes a role, or
  renames/deletes a permission → `forgetAll()` (a role can be global, so it
  affects users in every tenant).
- Anything that changes **one user's** roles or overrides → `forgetFor($user)`
  and `unsetRelation('roles')`.
- The registrar is a *scoped* singleton, so its in-memory memo and pinned
  tenant reset per request/job. Keep it that way for Octane and queues.

## Rules for changes

- Never hard-code table or column names. Use `config('permission.table_names.*')`
  and `config('permission.column_names.model_morph_key')`.
- Never reference `Role::class` / `Permission::class` for queries. Use
  `$registrar->roleClass()` / `permissionClass()` / `userClass()` so apps can
  swap models.
- New migrations: add the suffix to `PermissionServiceProvider::MIGRATIONS`
  (order matters) and guard with `hasTable` / `hasColumn`, so an already-
  published or already-migrated app is unaffected.
- No dependency on any app (`App\…`), CSS framework, or JS build. UI styles
  live in `resources/views/partials/assets.blade.php` under `.pm-*`.
- UI security lives in `Http/Controllers/Controller.php`: scope every query
  with `rolesInScope()` / `usersQuery()`, and check `canManageRole()` /
  `ensureCanGrant()` before writes. Add a test for every new rule.
- Keep the public API backward compatible within a major version. If you
  must break it, bump the major and add a section to README "Upgrading".

## Running things

```bash
composer install
composer test            # vendor/bin/pest
composer format          # vendor/bin/pint
```

Tests use Orchestra Testbench with in-memory SQLite, so no database setup is needed.

## Trying it inside a real app before releasing

In the app's `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "../smk-permission", "options": { "symlink": true } }
],
"require": { "buildwithsmk/laravel-tenant-permission": "*@dev" }
```

Then run `composer update buildwithsmk/laravel-tenant-permission`. Edits in this folder show up in the app immediately.

## Releasing

1. Update `CHANGELOG.md`. Don't add a `version` field to composer.json;
   Packagist reads versions from git tags.
2. `composer test && composer format`
3. Commit, then tag: `git tag v1.1.0 && git push origin main --tags`

**First-time publishing**

- Create a GitHub repo and push this folder.
- **Public:** sign in at packagist.org → Submit → paste the repo URL, then
  enable the GitHub hook so new tags appear automatically. Check first that
  the package is published as `buildwithsmk/laravel-tenant-permission`. Repo:
  `github.com/MSumon89/laravel-tenant-permission`. (The short vendor `smk` belongs
  to someone else on Packagist, so don't use it.) The PHP namespace stays `Smk\Permission`.
- **Private:** in each app, add
  `{ "type": "vcs", "url": "git@github.com:MSumon89/laravel-tenant-permission.git" }`
  to `repositories` and `composer require buildwithsmk/laravel-tenant-permission:^1.1`
  (or use Private Packagist / Satis).

Before the first public release, add a GitHub Actions workflow that runs
`composer test` on PHP 8.2–8.4 × Laravel 11/12/13.
