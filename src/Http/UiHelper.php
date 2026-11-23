<?php

namespace BuildWithSmk\TenantPermission\Http;

use BuildWithSmk\TenantPermission\PermissionRegistrar;

/**
 * Shared with every permission:: view as $pm.
 */
class UiHelper
{
    public function route(string $name, mixed $parameters = []): string
    {
        return route($this->routeName($name), $parameters);
    }

    public function routeName(string $name): string
    {
        return config('permission.ui.route_name_prefix', 'permission.ui.').$name;
    }

    public function is(string $pattern): bool
    {
        return request()->routeIs($this->routeName($pattern));
    }

    /**
     * "Tenant #5" / "Global scope" badge text, or null in a single app.
     */
    public function scopeLabel(): ?string
    {
        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->tenancyEnabled()) {
            return null;
        }

        $tenantId = $registrar->tenantIdFor(request()->user());

        return $tenantId === $registrar->globalTenantId() ? 'Global scope' : 'Tenant #'.$tenantId;
    }

    public function layout(): string
    {
        return (string) config('permission.ui.layout', 'permission::layouts.app');
    }

    public function section(): string
    {
        return (string) config('permission.ui.section', 'content');
    }
}
