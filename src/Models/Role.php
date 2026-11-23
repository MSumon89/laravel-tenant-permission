<?php

namespace BuildWithSmk\TenantPermission\Models;

use BuildWithSmk\TenantPermission\Exceptions\RoleDoesNotExist;
use BuildWithSmk\TenantPermission\PermissionRegistrar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;

/**
 * A named set of permissions inside one tenant scope.
 * tenant_id = global_tenant_id (0) makes it a global role that applies in
 * every tenant.
 *
 * - is_system: protected from deletion.
 * - is_admin:  holders get every permission in this role's tenant.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $guard_name
 * @property string|null $description
 * @property bool $is_system
 * @property bool $is_admin
 */
class Role extends Model
{
    protected $fillable = ['name', 'guard_name', 'tenant_id', 'description', 'is_system', 'is_admin'];

    protected $casts = [
        'is_system' => 'boolean',
        'is_admin' => 'boolean',
        'tenant_id' => 'integer',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->attributes['guard_name'] ??= config('permission.default_guard', 'web');
        $this->attributes['tenant_id'] ??= (int) config('permission.global_tenant_id', 0);
        $this->setTable(config('permission.table_names.roles', 'roles'));
    }

    protected static function booted(): void
    {
        static::deleted(fn () => app(PermissionRegistrar::class)->forgetAll());
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            app(PermissionRegistrar::class)->permissionClass(),
            config('permission.table_names.role_has_permissions', 'role_has_permissions'),
            'role_id',
            'permission_id'
        );
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            app(PermissionRegistrar::class)->userClass(),
            'model',
            config('permission.table_names.model_has_roles', 'model_has_roles'),
            'role_id',
            config('permission.column_names.model_morph_key', 'model_id')
        )->withPivot('tenant_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForTenant(Builder $query, int $tenantId): void
    {
        $query->where('tenant_id', $tenantId);
    }

    public function isGlobal(): bool
    {
        return (int) $this->tenant_id === (int) config('permission.global_tenant_id', 0);
    }

    public function isSuperAdminRole(): bool
    {
        return $this->isGlobal() && $this->name === config('permission.super_admin_role', 'Super Admin');
    }

    /**
     * Add one or more permissions: givePermissionTo('a', 'b') or (['a', 'b']).
     */
    public function givePermissionTo(string|Permission|array ...$permissions): static
    {
        $this->permissions()->syncWithoutDetaching($this->permissionIds($permissions));
        app(PermissionRegistrar::class)->forgetAll();

        return $this;
    }

    public function revokePermissionTo(string|Permission|array ...$permissions): static
    {
        $this->permissions()->detach($this->permissionIds($permissions));
        app(PermissionRegistrar::class)->forgetAll();

        return $this;
    }

    /**
     * Replace the role's permissions with exactly the given list.
     *
     * @param  array<int, string|Permission>  $permissions
     */
    public function syncPermissions(array $permissions): static
    {
        $this->permissions()->sync($this->permissionIds($permissions));
        app(PermissionRegistrar::class)->forgetAll();

        return $this;
    }

    public function hasPermissionTo(string|Permission $permission): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;

        return $this->permissions()->where('name', $name)->exists();
    }

    /**
     * @param  array<int, mixed>  $permissions
     * @return array<int, int>
     */
    protected function permissionIds(array $permissions): array
    {
        $permissionClass = app(PermissionRegistrar::class)->permissionClass();

        return collect(Arr::flatten($permissions))
            ->map(fn (string|Permission $permission): int => $permission instanceof Permission
                ? $permission->getKey()
                : $permissionClass::findByName($permission, $this->guard_name)->getKey())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Find (or fail) a role by name in a tenant scope. Without a tenant id
     * the current scope is used (global when tenancy is off).
     */
    public static function findByName(string $name, ?int $tenantId = null, ?string $guardName = null): static
    {
        $tenantId ??= app(PermissionRegistrar::class)->currentScopeId();
        $guardName ??= config('permission.default_guard', 'web');

        $role = static::query()
            ->where('name', $name)
            ->where('tenant_id', $tenantId)
            ->where('guard_name', $guardName)
            ->first();

        if (! $role) {
            throw RoleDoesNotExist::named($name, $tenantId, $guardName);
        }

        return $role;
    }

    public static function findOrCreate(string $name, ?int $tenantId = null, ?string $guardName = null): static
    {
        $tenantId ??= app(PermissionRegistrar::class)->currentScopeId();
        $guardName ??= config('permission.default_guard', 'web');

        return static::query()->firstOrCreate(['name' => $name, 'tenant_id' => $tenantId, 'guard_name' => $guardName]);
    }
}
