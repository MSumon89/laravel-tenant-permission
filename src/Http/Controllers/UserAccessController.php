<?php

namespace BuildWithSmk\TenantPermission\Http\Controllers;

use BuildWithSmk\TenantPermission\Models\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Assign roles and set per-user grant/deny overrides, in the actor's scope.
 */
class UserAccessController extends Controller
{
    public function index(Request $request): View
    {
        $search = (string) $request->string('search');
        $display = config('permission.ui.user_display_column', 'name');
        $columns = (array) config('permission.ui.user_search_columns', ['name', 'email']);

        $users = $this->usersQuery($request)
            ->with('roles')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($columns, $search): void {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            }))
            ->orderBy($display)
            ->paginate((int) config('permission.ui.per_page', 20))
            ->withQueryString();

        return view('permission::users.index', [
            'users' => $users,
            'tenantId' => $this->scopeTenantId($request),
            'displayColumn' => $display,
        ]);
    }

    public function edit(Request $request, string $user): View
    {
        $user = $this->findUser($request, $user);
        $tenantId = $this->scopeTenantId($request);
        $assignable = $this->assignableRoles($request);

        return view('permission::users.edit', [
            'user' => $user,
            'displayColumn' => config('permission.ui.user_display_column', 'name'),
            'roles' => $this->rolesInScope($request)->orderBy('name')->get(),
            'assignableIds' => $assignable->modelKeys(),
            'heldIds' => $user->roles->where('tenant_id', $tenantId)->modelKeys(),
            'permissionsByModule' => $this->permissionsByModule(),
            'overrides' => $user->getPermissionOverrides($tenantId),
            'viaRoles' => $user->getPermissionsViaRoles($tenantId),
            'effective' => $user->getAllPermissions($tenantId),
            'grantable' => $this->grantablePermissions($request),
            'isSuperAdmin' => $user->isSuperAdmin(),
            'isTenantAdmin' => $user->isTenantAdmin($tenantId),
        ]);
    }

    public function update(Request $request, string $user): RedirectResponse
    {
        $user = $this->findUser($request, $user);
        $tenantId = $this->scopeTenantId($request);

        $request->validate([
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer'],
            'overrides' => ['sometimes', 'array'],
        ]);

        $roleIds = $this->resolveRoleIds($request, $user, $tenantId);
        $overrides = $this->resolveOverrides($request, $user, $tenantId);

        $user->getConnection()->transaction(function () use ($user, $roleIds, $overrides, $tenantId): void {
            $user->syncRoles($roleIds, $tenantId);
            $user->syncPermissionOverrides($overrides, $tenantId);
        });

        return $this->done('users.edit', 'Access updated.', $user->getKey());
    }

    /**
     * Requested roles the actor may assign, plus roles the user already
     * holds that the actor may not touch (those stay as they are).
     *
     * @return array<int, int>
     */
    protected function resolveRoleIds(Request $request, Model $user, int $tenantId): array
    {
        $requested = array_map('intval', $request->input('roles', []));
        $assignable = $this->assignableRoles($request)->modelKeys();
        $held = $user->roles->where('tenant_id', $tenantId);
        $heldIds = $held->modelKeys();

        abort_if(array_diff($requested, $assignable, $heldIds) !== [], 403, 'You cannot assign one of those roles.');

        $final = array_values(array_unique(array_merge(
            array_intersect($requested, $assignable),
            array_diff($heldIds, $assignable)
        )));

        // Don't let an admin lock themselves out by removing their own admin role.
        if ($user->is($request->user())) {
            $ownAdminRoles = $held->filter(fn (Role $role): bool => $role->is_admin || $role->isSuperAdminRole())->modelKeys();

            if ($ownAdminRoles !== [] && array_intersect($ownAdminRoles, $final) === []) {
                throw ValidationException::withMessages(['roles' => 'You cannot remove your own admin role.']);
            }
        }

        return $final;
    }

    /**
     * @return array<string, string>
     */
    protected function resolveOverrides(Request $request, Model $user, int $tenantId): array
    {
        $known = $this->registrar->permissionClass()::query()
            ->where('guard_name', $this->registrar->defaultGuard())
            ->pluck('name')
            ->all();

        $requested = collect((array) $request->input('overrides', []))
            ->filter(fn ($type, $name): bool => in_array($type, ['grant', 'deny'], true) && in_array($name, $known, true))
            ->all();

        $current = $user->getPermissionOverrides($tenantId);
        $grantable = $this->grantablePermissions($request);

        if ($grantable === null) {
            return $requested;
        }

        // A non-admin may only change overrides of permissions they hold.
        $changed = collect(array_unique(array_merge(array_keys($requested), array_keys($current))))
            ->filter(fn (string $name): bool => ($requested[$name] ?? null) !== ($current[$name] ?? null))
            ->all();

        $this->ensureCanGrant($request, $changed);

        return $requested;
    }

    /**
     * @return Collection<int, Role>
     */
    protected function assignableRoles(Request $request): Collection
    {
        return $this->rolesInScope($request)
            ->with('permissions:id,name')
            ->get()
            ->filter(fn (Role $role): bool => $this->canManageRole($request, $role))
            ->values();
    }

    protected function findUser(Request $request, string $id): Model
    {
        $user = $this->usersQuery($request)->findOrFail($id);

        abort_unless(method_exists($user, 'syncRoles'), 500, 'The user model must use the HasRolesAndPermissions trait.');

        return $user;
    }
}
