<?php

namespace Smk\Permission\Facades;

use Illuminate\Support\Facades\Facade;
use Smk\Permission\PermissionRegistrar;

/**
 * @method static \Smk\Permission\PermissionRegistrar setCurrentTenantId(?int $tenantId)
 * @method static int|null getCurrentTenantId()
 * @method static int currentScopeId()
 * @method static int tenantIdFor(?\Illuminate\Database\Eloquent\Model $model = null)
 * @method static bool tenancyEnabled()
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
