<?php

namespace BuildWithSmk\TenantPermission\Tests;

use BuildWithSmk\TenantPermission\Tests\Fixtures\OfficeBranchResolver;

/**
 * Branches enabled at boot, so the switch route is registered. Tenancy
 * stays off (an app with its own offices and no tenancy, like cims);
 * single tests turn it on.
 */
abstract class BranchTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('permission.branches.enabled', true);
        $app['config']->set('permission.branches.resolver', OfficeBranchResolver::class);
    }
}
