<?php

namespace BuildWithSmk\TenantPermission\Http\Controllers;

use BuildWithSmk\TenantPermission\Models\Permission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The permission catalog is global (shared by every tenant). In a
 * multi-tenant app only a Super Admin may change it. Tenant managers get a
 * read-only list.
 */
class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $search = (string) $request->string('search');

        $permissions = $this->registrar->permissionClass()::query()
            ->where('guard_name', $this->registrar->defaultGuard())
            ->withCount('roles')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('display_name', 'like', "%{$search}%")))
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => $permission->module ?: 'general');

        return view('permission::permissions.index', [
            'permissionsByModule' => $permissions,
            'canManageCatalog' => $this->canManageCatalog($request),
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureCanManageCatalog($request);

        return view('permission::permissions.form', ['permission' => new ($this->registrar->permissionClass())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManageCatalog($request);

        $guard = $this->registrar->defaultGuard();
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]+)*$/',
                Rule::unique(config('permission.table_names.permissions', 'permissions'), 'name')->where('guard_name', $guard),
            ],
            ...$this->detailRules(),
        ], ['name.regex' => 'Use letters, numbers, dashes and dots, e.g. "posts.edit".']);

        $permission = $this->registrar->permissionClass()::query()->create($data + [
            'guard_name' => $guard,
            'status' => $request->boolean('status', true),
        ]);

        return $this->done('permissions.index', "Permission \"{$permission->name}\" created.");
    }

    public function edit(Request $request, string $permission): View
    {
        $this->ensureCanManageCatalog($request);

        return view('permission::permissions.form', ['permission' => $this->findPermission($permission)]);
    }

    /**
     * The name is not editable: code checks permissions by name, so a
     * rename would silently break them. Create a new one instead.
     */
    public function update(Request $request, string $permission): RedirectResponse
    {
        $this->ensureCanManageCatalog($request);

        $permission = $this->findPermission($permission);
        $permission->update($request->validate($this->detailRules()) + ['status' => $request->boolean('status')]);

        return $this->done('permissions.index', "Permission \"{$permission->name}\" updated.");
    }

    public function destroy(Request $request, string $permission): RedirectResponse
    {
        $this->ensureCanManageCatalog($request);

        $permission = $this->findPermission($permission);
        $permission->delete();

        return $this->done('permissions.index', "Permission \"{$permission->name}\" deleted.");
    }

    protected function canManageCatalog(Request $request): bool
    {
        return $this->registrar->tenancyEnabled()
            ? $this->actorIsSuperAdmin($request)
            : $this->actorIsAdmin($request) || $request->user()->hasPermissionTo('access-control.manage');
    }

    protected function ensureCanManageCatalog(Request $request): void
    {
        abort_unless($this->canManageCatalog($request), 403, 'Only a Super Admin can change the permission catalog.');
    }

    protected function findPermission(string $id): Permission
    {
        return $this->registrar->permissionClass()::query()
            ->where('guard_name', $this->registrar->defaultGuard())
            ->findOrFail((int) $id);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function detailRules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
