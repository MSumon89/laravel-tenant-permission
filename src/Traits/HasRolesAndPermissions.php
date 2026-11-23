<?php

namespace BuildWithSmk\TenantPermission\Traits;

use BuildWithSmk\TenantPermission\Models\Permission;
use BuildWithSmk\TenantPermission\Models\Role;
use BuildWithSmk\TenantPermission\PermissionRegistrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Add to your User model (or any Eloquent model that should hold roles).
 *
 * Effective permissions in a tenant scope =
 *   (permissions of the user's roles in that tenant + global roles)
 *   + direct grants − direct denies.
 * Super Admin and tenant-admin (is_admin role) users hold every permission.
 *
 * Every $tenantId argument is optional. When omitted, the scope comes from
 * PermissionRegistrar::tenantIdFor($this): always global in a single app.
 *
 * @mixin Model
 */
trait HasRolesAndPermissions
{
    /**
     * Polymorphic pivots have no foreign key to your users table, so clean
     * them up when the model is (force) deleted.
     */
    public static function bootHasRolesAndPermissions(): void
    {
        static::deleting(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $morphKey = config('permission.column_names.model_morph_key', 'model_id');

            $model->roles()->detach();

            DB::table(config('permission.table_names.model_has_permissions'))
                ->where($morphKey, $model->getKey())
                ->where('model_type', $model->getMorphClass())
                ->delete();

            app(PermissionRegistrar::class)->forgetFor($model);
        });
    }

    protected function permissionRegistrar(): PermissionRegistrar
    {
        return app(PermissionRegistrar::class);
    }

    /**
     * Every role the model holds, across all tenants.
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            $this->permissionRegistrar()->roleClass(),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key', 'model_id'),
            'role_id'
        )->withPivot('tenant_id');
    }

    /**
     * Direct grant/deny overrides, across all tenants.
     */
    public function permissionOverrides(): MorphToMany
    {
        return $this->morphToMany(
            $this->permissionRegistrar()->permissionClass(),
            'model',
            config('permission.table_names.model_has_permissions'),
            config('permission.column_names.model_morph_key', 'model_id'),
            'permission_id'
        )->withPivot(['tenant_id', 'type'])->withTimestamps();
    }

    /**
     * The tenant scope this model's checks run in.
     */
    public function resolveTenantId(): int
    {
        return $this->permissionRegistrar()->tenantIdFor($this);
    }

    // -----------------------------------------------------------------
    // Roles
    // -----------------------------------------------------------------

    public function assignRole(string|Role $role, ?int $tenantId = null): static
    {
        $role = $this->resolveRole($role, $tenantId);

        $this->roles()->syncWithoutDetaching([$role->getKey() => ['tenant_id' => $role->tenant_id]]);

        return $this->rolesChanged();
    }

    public function removeRole(string|Role $role, ?int $tenantId = null): static
    {
        $role = $this->resolveRole($role, $tenantId);

        $this->roles()->wherePivot('tenant_id', $role->tenant_id)->detach($role->getKey());

        return $this->rolesChanged();
    }

    /**
     * Replace the model's roles in one tenant scope with exactly these.
     * Roles held in other tenants are not touched.
     *
     * @param  array<int, string|int|Role>  $roles  names, ids or models
     */
    public function syncRoles(array $roles, ?int $tenantId = null): static
    {
        $tenantId ??= $this->resolveTenantId();
        $roleClass = $this->permissionRegistrar()->roleClass();

        $attach = collect($roles)
            ->map(fn (string|int|Role $role): Role => is_int($role) ? $roleClass::query()->findOrFail($role) : $this->resolveRole($role, $tenantId))
            ->mapWithKeys(fn (Role $role): array => [$role->getKey() => ['tenant_id' => $role->tenant_id]])
            ->all();

        DB::transaction(function () use ($tenantId, $attach): void {
            $this->roles()->wherePivot('tenant_id', $tenantId)->detach();
            $this->roles()->syncWithoutDetaching($attach);
        });

        return $this->rolesChanged();
    }

    /**
     * True if the model holds any of the given roles in the tenant scope
     * (global roles count in every tenant).
     *
     * @param  string|Role|array<int, string|Role>  $roles
     */
    public function hasRole(string|Role|array $roles, ?int $tenantId = null): bool
    {
        $held = $this->rolesForTenant($tenantId);

        foreach (Arr::wrap($roles) as $role) {
            $matches = $role instanceof Role
                ? $held->contains(fn (Role $r): bool => $r->is($role))
                : $held->contains(fn (Role $r): bool => $r->name === $role);

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string|Role>  $roles
     */
    public function hasAnyRole(array $roles, ?int $tenantId = null): bool
    {
        return $this->hasRole($roles, $tenantId);
    }

    /**
     * @return Collection<int, string>
     */
    public function getRoleNames(?int $tenantId = null): Collection
    {
        return $this->rolesForTenant($tenantId)->pluck('name')->values();
    }

    /**
     * Roles that apply in a tenant scope: that tenant's roles plus global ones.
     *
     * @return Collection<int, Role>
     */
    public function rolesForTenant(?int $tenantId = null): Collection
    {
        $tenantId ??= $this->resolveTenantId();

        return $this->roles
            ->filter(fn (Role $role): bool => (int) $role->tenant_id === $tenantId || $role->isGlobal())
            ->values();
    }

    /**
     * True if the model holds the global Super Admin role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->roles->contains(fn (Role $role): bool => $role->isSuperAdminRole());
    }

    /**
     * True if the model holds an is_admin role in the tenant scope. Such
     * users hold every permission in that tenant. In a single app (tenancy
     * off) an is_admin role is simply an "all permissions" role.
     */
    public function isTenantAdmin(?int $tenantId = null): bool
    {
        $tenantId ??= $this->resolveTenantId();

        return $this->roles->contains(fn (Role $role): bool => $role->is_admin && (int) $role->tenant_id === $tenantId);
    }

    protected function resolveRole(string|Role $role, ?int $tenantId = null): Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        return $this->permissionRegistrar()->roleClass()::findByName($role, $tenantId ?? $this->resolveTenantId());
    }

    protected function rolesChanged(): static
    {
        $this->unsetRelation('roles');
        $this->permissionRegistrar()->forgetFor($this);

        return $this;
    }

    // -----------------------------------------------------------------
    // Direct permission overrides (grant / deny)
    // -----------------------------------------------------------------

    /**
     * Grant a permission directly, on top of what the roles give.
     */
    public function givePermissionTo(string|Permission $permission, ?int $tenantId = null): static
    {
        return $this->setPermissionOverride($permission, 'grant', $tenantId);
    }

    /**
     * Deny a permission even if a role grants it. Deny always wins.
     */
    public function denyPermissionTo(string|Permission $permission, ?int $tenantId = null): static
    {
        return $this->setPermissionOverride($permission, 'deny', $tenantId);
    }

    /**
     * Remove a direct grant/deny so the roles decide again.
     */
    public function clearPermissionOverride(string|Permission $permission, ?int $tenantId = null): static
    {
        $tenantId ??= $this->resolveTenantId();

        $this->overrideQuery($tenantId)
            ->where('permission_id', $this->resolvePermission($permission)->getKey())
            ->delete();

        $this->permissionRegistrar()->forgetFor($this);

        return $this;
    }

    /**
     * Replace every override in a tenant scope with the given map.
     *
     * @param  array<string, 'grant'|'deny'>  $overrides  permission name => type
     */
    public function syncPermissionOverrides(array $overrides, ?int $tenantId = null): static
    {
        $tenantId ??= $this->resolveTenantId();
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $now = now();

        $ids = $this->permissionRegistrar()->permissionClass()::query()
            ->whereIn('name', array_keys($overrides))
            ->where('guard_name', $this->permissionRegistrar()->defaultGuard())
            ->pluck('id', 'name');

        $rows = collect($overrides)
            ->filter(fn (string $type, string $name): bool => in_array($type, ['grant', 'deny'], true) && $ids->has($name))
            ->map(fn (string $type, string $name): array => [
                'permission_id' => $ids[$name],
                $morphKey => $this->getKey(),
                'model_type' => $this->getMorphClass(),
                'tenant_id' => $tenantId,
                'type' => $type,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($tenantId, $rows): void {
            $this->overrideQuery($tenantId)->delete();

            if ($rows !== []) {
                DB::table(config('permission.table_names.model_has_permissions'))->insert($rows);
            }
        });

        $this->permissionRegistrar()->forgetFor($this);

        return $this;
    }

    /**
     * Overrides in a tenant scope as a name => 'grant'|'deny' map.
     *
     * @return array<string, string>
     */
    public function getPermissionOverrides(?int $tenantId = null): array
    {
        return $this->permissionOverrides()
            ->wherePivot('tenant_id', $tenantId ?? $this->resolveTenantId())
            ->get()
            ->mapWithKeys(fn (Permission $permission): array => [$permission->name => $permission->pivot->type])
            ->all();
    }

    protected function setPermissionOverride(string|Permission $permission, string $type, ?int $tenantId = null): static
    {
        $tenantId ??= $this->resolveTenantId();
        $permission = $this->resolvePermission($permission);
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');

        DB::table(config('permission.table_names.model_has_permissions'))->updateOrInsert(
            [
                'permission_id' => $permission->getKey(),
                $morphKey => $this->getKey(),
                'model_type' => $this->getMorphClass(),
                'tenant_id' => $tenantId,
            ],
            ['type' => $type, 'created_at' => now(), 'updated_at' => now()]
        );

        $this->permissionRegistrar()->forgetFor($this);

        return $this;
    }

    protected function overrideQuery(int $tenantId): Builder
    {
        return DB::table(config('permission.table_names.model_has_permissions'))
            ->where(config('permission.column_names.model_morph_key', 'model_id'), $this->getKey())
            ->where('model_type', $this->getMorphClass())
            ->where('tenant_id', $tenantId);
    }

    protected function resolvePermission(string|Permission $permission): Permission
    {
        if ($permission instanceof Permission) {
            return $permission;
        }

        return $this->permissionRegistrar()->permissionClass()::findByName($permission);
    }

    // -----------------------------------------------------------------
    // Effective permission resolution
    // -----------------------------------------------------------------

    /**
     * Every permission name the model effectively holds in a tenant scope.
     *
     * @return array<int, string>
     */
    public function getAllPermissions(?int $tenantId = null): array
    {
        $tenantId ??= $this->resolveTenantId();
        $registrar = $this->permissionRegistrar();

        if ($this->isSuperAdmin() || $this->isTenantAdmin($tenantId)) {
            return $registrar->permissionClass()::query()->pluck('name')->unique()->values()->all();
        }

        return $registrar->rememberEffectivePermissions($this, $tenantId, function () use ($tenantId): array {
            $overrides = $this->getPermissionOverrides($tenantId);
            $granted = array_keys(array_filter($overrides, fn (string $type): bool => $type === 'grant'));
            $denied = array_keys(array_filter($overrides, fn (string $type): bool => $type === 'deny'));

            $effective = array_unique(array_merge($this->getPermissionsViaRoles($tenantId), $granted));

            return array_values(array_diff($effective, $denied));
        });
    }

    /**
     * Permission names given by the model's roles in a tenant scope
     * (before overrides).
     *
     * @return array<int, string>
     */
    public function getPermissionsViaRoles(?int $tenantId = null): array
    {
        $roleIds = $this->rolesForTenant($tenantId)->modelKeys();

        if ($roleIds === []) {
            return [];
        }

        $rolesTable = config('permission.table_names.roles', 'roles');

        return $this->permissionRegistrar()->permissionClass()::query()
            ->whereHas('roles', fn ($query) => $query->whereIn($rolesTable.'.id', $roleIds))
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermissionTo(string|Permission $permission, ?int $tenantId = null): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;

        return in_array($name, $this->getAllPermissions($tenantId), true);
    }

    /**
     * @param  array<int, string>|string  $permissions
     */
    public function hasAnyPermission(array|string $permissions, ?int $tenantId = null): bool
    {
        return array_intersect(Arr::wrap($permissions), $this->getAllPermissions($tenantId)) !== [];
    }

    /**
     * @param  array<int, string>|string  $permissions
     */
    public function hasAllPermissions(array|string $permissions, ?int $tenantId = null): bool
    {
        return array_diff(Arr::wrap($permissions), $this->getAllPermissions($tenantId)) === [];
    }

    protected function forgetCachedPermissions(): void
    {
        $this->permissionRegistrar()->forgetFor($this);
    }
}
