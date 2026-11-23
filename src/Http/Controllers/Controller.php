<?php

namespace Smk\Permission\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Smk\Permission\Http\UiHelper;
use Smk\Permission\Models\Permission;
use Smk\Permission\Models\Role;
use Smk\Permission\PermissionRegistrar;

/**
 * Shared rules for the built-in UI.
 *
 * - Every screen works in the ACTING user's tenant scope (global in a
 *   single app). Roles and users of other tenants answer 404.
 * - "Admins" (Super Admin, or an is_admin role in the scope) can do
 *   everything in the scope.
 * - Other managers can only grant permissions they hold themselves, and
 *   only assign roles made of such permissions.
 * - Only a Super Admin can see or assign the Super Admin role.
 */
abstract class Controller extends BaseController
{
    public function __construct(protected PermissionRegistrar $registrar) {}

    protected function scopeTenantId(Request $request): int
    {
        return $this->registrar->tenantIdFor($request->user());
    }

    protected function actorIsSuperAdmin(Request $request): bool
    {
        $user = $request->user();

        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    protected function actorIsAdmin(Request $request): bool
    {
        return $this->actorIsSuperAdmin($request)
            || $request->user()->isTenantAdmin($this->scopeTenantId($request));
    }

    /**
     * Permission names the actor may hand out, or null for "all".
     *
     * @return array<int, string>|null
     */
    protected function grantablePermissions(Request $request): ?array
    {
        if ($this->actorIsAdmin($request)) {
            return null;
        }

        return $request->user()->getAllPermissions($this->scopeTenantId($request));
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    protected function ensureCanGrant(Request $request, array $permissionNames): void
    {
        $grantable = $this->grantablePermissions($request);

        abort_if(
            $grantable !== null && array_diff($permissionNames, $grantable) !== [],
            403,
            'You can only grant permissions you hold yourself.'
        );
    }

    /**
     * @return Builder<Role>
     */
    protected function rolesInScope(Request $request): Builder
    {
        $tenantId = $this->scopeTenantId($request);

        return $this->registrar->roleClass()::query()
            ->where('tenant_id', $tenantId)
            ->where('guard_name', $this->registrar->defaultGuard())
            ->when(
                $tenantId === $this->registrar->globalTenantId() && ! $this->actorIsSuperAdmin($request),
                fn (Builder $query) => $query->where('name', '!=', $this->registrar->superAdminRoleName())
            );
    }

    protected function findRoleInScope(Request $request, int|string $id): Role
    {
        return $this->rolesInScope($request)->findOrFail((int) $id);
    }

    /**
     * Can the actor edit/assign this role at all?
     */
    protected function canManageRole(Request $request, Role $role): bool
    {
        if ($role->isSuperAdminRole()) {
            return $this->actorIsSuperAdmin($request);
        }

        if ($role->is_admin) {
            return $this->actorIsAdmin($request);
        }

        $grantable = $this->grantablePermissions($request);

        return $grantable === null
            || array_diff($role->permissions->pluck('name')->all(), $grantable) === [];
    }

    /**
     * @return Collection<string, Collection<int, Permission>>
     */
    protected function permissionsByModule(): Collection
    {
        return $this->registrar->permissionClass()::query()
            ->where('guard_name', $this->registrar->defaultGuard())
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => $permission->module ?: (Str::contains($permission->name, '.') ? Str::before($permission->name, '.') : 'general'))
            ->sortKeys();
    }

    /**
     * Users visible in the actor's scope. With tenancy on, a tenant scope
     * lists only that tenant's users: via a `scopeInPermissionTenant($query,
     * $tenantId)` on your User model if present, otherwise the configured
     * tenant column. The global scope lists every user.
     *
     * @return Builder<Model>
     */
    protected function usersQuery(Request $request): Builder
    {
        $userClass = $this->registrar->userClass();
        $query = $userClass::query();
        $tenantId = $this->scopeTenantId($request);

        if (! $this->registrar->tenancyEnabled() || $tenantId === $this->registrar->globalTenantId()) {
            return $query;
        }

        if (method_exists($userClass, 'scopeInPermissionTenant')) {
            return $query->inPermissionTenant($tenantId);
        }

        $column = config('permission.tenancy.tenant_column');

        return $column ? $query->where($column, $tenantId) : $query;
    }

    protected function done(string $route, string $message, mixed $parameters = []): RedirectResponse
    {
        return redirect()
            ->route(app(UiHelper::class)->routeName($route), $parameters)
            ->with('permission_status', $message);
    }
}
