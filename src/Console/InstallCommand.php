<?php

namespace BuildWithSmk\TenantPermission\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'permission:install
        {--migrations : Also copy the migrations into database/migrations (they auto-load otherwise)}
        {--views : Also copy the UI views into resources/views/vendor/permission}';

    protected $description = 'Publish the permission config (and optionally migrations/views) and print the next steps';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'permission-config']);

        if ($this->option('migrations')) {
            $this->call('vendor:publish', ['--tag' => 'permission-migrations']);
        }

        if ($this->option('views')) {
            $this->call('vendor:publish', ['--tag' => 'permission-views']);
        }

        $this->newLine();
        $this->components->info('buildwithsmk/laravel-tenant-permission installed. Next steps:');
        $this->components->bulletList([
            'Review config/permission.php (table names must be final before migrating).',
            'Multi-tenant app? Set PERMISSION_TENANCY=true in .env.',
            'Run: php artisan migrate',
            'Add the BuildWithSmk\TenantPermission\Traits\HasRolesAndPermissions trait to your User model.',
            'Seed the basics: php artisan db:seed --class="BuildWithSmk\TenantPermission\Database\Seeders\PermissionSeeder"',
            'Make yourself Super Admin: php artisan permission:super-admin you@example.com',
            'Optional UI: set PERMISSION_UI=true and open /'.config('permission.ui.prefix', 'access-control'),
        ]);

        return self::SUCCESS;
    }
}
