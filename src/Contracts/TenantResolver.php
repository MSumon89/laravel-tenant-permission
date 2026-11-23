<?php

namespace Smk\Permission\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Decides which tenant a user's permission checks run in when tenancy is
 * enabled and no tenant has been pinned with setCurrentTenantId().
 *
 * Bind your own implementation via config('permission.tenancy.resolver'),
 * e.g. to read the tenant from a tenancy package's "current tenant".
 */
interface TenantResolver
{
    /**
     * Return the tenant id, or null to fall back to the global scope.
     */
    public function resolve(Model $model): ?int;
}
