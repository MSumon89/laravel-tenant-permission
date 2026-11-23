<?php

namespace BuildWithSmk\TenantPermission\Console;

use BuildWithSmk\TenantPermission\PermissionRegistrar;
use Illuminate\Console\Command;

class CacheResetCommand extends Command
{
    protected $signature = 'permission:cache-reset';

    protected $description = 'Invalidate every cached effective-permission list';

    public function handle(PermissionRegistrar $registrar): int
    {
        $registrar->forgetAll();

        $this->components->info('Permission cache reset.');

        return self::SUCCESS;
    }
}
