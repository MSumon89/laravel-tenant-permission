<?php

namespace Smk\Permission\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Smk\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $roles = $this->rolesInScope($request)
            ->withCount(['permissions', 'users'])
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate((int) config('permission.ui.per_page', 20))
            ->withQueryString();

        return view('permission::roles.index', ['roles' => $roles]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new ($this->registrar->roleClass()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->ensureCanGrant($request, $data['permissions']);

        $role = $this->registrar->roleClass()::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'tenant_id' => $this->scopeTenantId($request),
            'guard_name' => $this->registrar->defaultGuard(),
            'is_admin' => $this->actorIsAdmin($request) && $request->boolean('is_admin'),
        ]);

        $role->syncPermissions($data['permissions']);

        return $this->done('roles.index', "Role \"{$role->name}\" created.");
    }

    public function edit(Request $request, string $role): View
    {
        $role = $this->findRoleInScope($request, $role);
        abort_unless($this->canManageRole($request, $role), 403, 'You cannot change this role.');

        return $this->form($request, $role);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $role = $this->findRoleInScope($request, $role);
        abort_unless($this->canManageRole($request, $role), 403, 'You cannot change this role.');

        $data = $this->validated($request, $role);
        $current = $role->permissions->pluck('name')->all();
        $grantable = $this->grantablePermissions($request);

        // Permissions the actor cannot grant are left exactly as they were.
        $requested = $data['permissions'];
        if ($grantable !== null) {
            $this->ensureCanGrant($request, array_diff($requested, $current));
            $requested = array_merge(array_intersect($requested, $grantable), array_diff($current, $grantable));
        }

        $role->fill([
            'name' => $this->nameIsLocked($role) ? $role->name : $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if ($this->actorIsAdmin($request) && ! $role->isSuperAdminRole()) {
            $role->is_admin = $request->boolean('is_admin');
        }

        $role->save();
        $role->syncPermissions(array_values(array_unique($requested)));

        return $this->done('roles.index', "Role \"{$role->name}\" updated.");
    }

    public function destroy(Request $request, string $role): RedirectResponse
    {
        $role = $this->findRoleInScope($request, $role);
        abort_unless($this->canManageRole($request, $role), 403, 'You cannot delete this role.');
        abort_if($role->is_system, 403, 'System roles cannot be deleted.');

        $role->delete();

        return $this->done('roles.index', "Role \"{$role->name}\" deleted.");
    }

    protected function form(Request $request, Role $role): View
    {
        return view('permission::roles.form', [
            'role' => $role,
            'permissionsByModule' => $this->permissionsByModule(),
            'selected' => $role->exists ? $role->permissions->pluck('name')->all() : [],
            'grantable' => $this->grantablePermissions($request),
            'canSetAdmin' => $this->actorIsAdmin($request) && ! $role->isSuperAdminRole(),
            'nameLocked' => $role->exists && $this->nameIsLocked($role),
        ]);
    }

    protected function nameIsLocked(Role $role): bool
    {
        return $role->is_system || $role->isSuperAdminRole();
    }

    /**
     * @return array{name: string, description?: string|null, permissions: array<int, string>}
     */
    protected function validated(Request $request, ?Role $role = null): array
    {
        $tenantId = $this->scopeTenantId($request);
        $guard = $this->registrar->defaultGuard();
        $rolesTable = config('permission.table_names.roles', 'roles');

        $data = $request->validate([
            'name' => [
                Rule::requiredIf(! $role || ! $this->nameIsLocked($role)),
                'string',
                'max:255',
                Rule::unique($rolesTable, 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('guard_name', $guard))
                    ->ignore($role?->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_admin' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists(config('permission.table_names.permissions', 'permissions'), 'name')->where('guard_name', $guard)],
        ]);

        $data['permissions'] = array_values($data['permissions'] ?? []);
        $data['name'] ??= $role?->name;

        return $data;
    }
}
