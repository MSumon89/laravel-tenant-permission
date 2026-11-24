<?php

namespace BuildWithSmk\TenantPermission\Middleware;

use BuildWithSmk\TenantPermission\PermissionRegistrar;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the current branch for the request and pins it on the registrar,
 * so permission checks and BelongsToBranch models use it.
 *
 * Selected branch = the session value if the user may use it, otherwise the
 * user's home branch (or "All branches" with branches.default_all),
 * otherwise their first accessible branch. "All branches" (0) is only
 * allowed for users with more than one branch.
 *
 * Add it to the `web` group (after the session starts), or before any
 * `permission:` / `tenant_role:` middleware on your routes.
 */
class ResolveBranch
{
    public function __construct(protected PermissionRegistrar $registrar) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($this->registrar->branchesEnabled() && $user instanceof Model) {
            [$branchId, $accessible] = static::resolveFor($this->registrar, $user, $this->requested($request));

            $this->registrar->setAccessibleBranchIds($accessible)->setCurrentBranchId($branchId);
        }

        return $next($request);
    }

    /**
     * @return array{0: int, 1: array<int, int>} [selected branch, accessible branches]
     */
    public static function resolveFor(PermissionRegistrar $registrar, Model $user, ?int $requested): array
    {
        $resolver = $registrar->branchResolver();
        $accessible = array_values(array_unique(array_map('intval', $resolver->accessibleBranchIds($user))));

        $requested ??= config('permission.branches.default_all', false) ? PermissionRegistrar::ALL_BRANCHES : null;

        if ($requested === PermissionRegistrar::ALL_BRANCHES && count($accessible) > 1) {
            return [PermissionRegistrar::ALL_BRANCHES, $accessible];
        }

        if ($requested !== null && in_array($requested, $accessible, true)) {
            return [$requested, $accessible];
        }

        $home = $resolver->defaultBranchId($user);

        if ($home !== null && in_array($home, $accessible, true)) {
            return [$home, $accessible];
        }

        // No usable branch at all: "All branches" over an empty list, which
        // BelongsToBranch turns into no rows.
        return [$accessible[0] ?? PermissionRegistrar::ALL_BRANCHES, $accessible];
    }

    protected function requested(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get(config('permission.branches.session_key', 'permission_branch_id'));

        return is_numeric($value) ? (int) $value : null;
    }
}
