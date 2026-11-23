<?php

namespace Smk\Permission\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Smk\Permission\Contracts\BelongsToTenant;
use Smk\Permission\Contracts\TenantResolver;

/**
 * Uses $model->getTenantId() when available (BelongsToTenant contract),
 * otherwise the attribute named by config('permission.tenancy.tenant_column').
 */
class DefaultTenantResolver implements TenantResolver
{
    public function resolve(Model $model): ?int
    {
        if ($model instanceof BelongsToTenant || method_exists($model, 'getTenantId')) {
            $tenantId = $model->getTenantId();
        } else {
            $column = config('permission.tenancy.tenant_column', 'tenant_id');
            $tenantId = $column ? $model->getAttribute($column) : null;
        }

        return $tenantId === null ? null : (int) $tenantId;
    }
}
