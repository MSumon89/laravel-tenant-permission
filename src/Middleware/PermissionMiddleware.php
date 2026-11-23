<?php

namespace BuildWithSmk\TenantPermission\Middleware;

use BuildWithSmk\TenantPermission\Exceptions\UnauthorizedException;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Passes if the user holds ANY of the listed permissions.
     *
     * ->middleware('permission:posts.edit')
     * ->middleware('permission:posts.edit|posts.delete')
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException;
        }

        $permissions = collect($permissions)
            ->flatMap(fn (string $permission): array => explode('|', $permission))
            ->map(fn (string $permission): string => trim($permission))
            ->filter()
            ->values()
            ->all();

        // Super Admin and tenant admins hold every permission, so
        // hasAnyPermission() already covers them.
        if (! method_exists($user, 'hasAnyPermission') || ! $user->hasAnyPermission($permissions)) {
            throw UnauthorizedException::forPermissions($permissions);
        }

        return $next($request);
    }
}
