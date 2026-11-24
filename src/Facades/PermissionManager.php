<?php

namespace BuildWithSmk\TenantPermission\Facades;

use BuildWithSmk\TenantPermission\PermissionRegistrar;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \BuildWithSmk\TenantPermission\PermissionRegistrar setCurrentTenantId(?int $tenantId)
 * @method static int|null getCurrentTenantId()
 * @method static int currentScopeId()
 * @method static int tenantIdFor(?\Illuminate\Database\Eloquent\Model $model = null)
 * @method static bool tenancyEnabled()
 * @method static bool branchesEnabled()
 * @method static \BuildWithSmk\TenantPermission\PermissionRegistrar setCurrentBranchId(?int $branchId)
 * @method static int|null getCurrentBranchId()
 * @method static \BuildWithSmk\TenantPermission\PermissionRegistrar setAccessibleBranchIds(?array $branchIds)
 * @method static array|null getAccessibleBranchIds()
 * @method static bool isAllBranches()
 * @method static int branchIdFor(?\Illuminate\Database\Eloquent\Model $model = null)
 * @method static array|null visibleBranchIds()
 * @method static void forgetFor(\Illuminate\Database\Eloquent\Model $model, ?int $tenantId = null)
 * @method static void forgetAll()
 *
 * @see PermissionRegistrar
 */
class PermissionManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PermissionRegistrar::class;
    }
}
