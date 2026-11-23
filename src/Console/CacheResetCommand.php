<?php

namespace Smk\Permission\Console;

use Illuminate\Console\Command;
use Smk\Permission\PermissionRegistrar;

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
