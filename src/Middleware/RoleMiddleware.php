<?php

namespace BuildWithSmk\TenantPermission\Middleware;

use BuildWithSmk\TenantPermission\Exceptions\UnauthorizedException;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Passes if the user holds ANY of the listed roles in their current
     * tenant scope (global roles count everywhere). Super Admin always passes.
     *
     * ->middleware('tenant_role:Manager')
     * ->middleware('tenant_role:Manager|Cashier')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException;
        }

        $roles = collect($roles)
            ->flatMap(fn (string $role): array => explode('|', $role))
            ->map(fn (string $role): string => trim($role))
            ->filter()
            ->values()
            ->all();

        if (! method_exists($user, 'hasRole')) {
            throw UnauthorizedException::forRoles($roles);
        }

        if ($user->isSuperAdmin() || $user->hasRole($roles)) {
            return $next($request);
        }

        throw UnauthorizedException::forRoles($roles);
    }
}
