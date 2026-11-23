<?php

namespace BuildWithSmk\TenantPermission;

use BuildWithSmk\TenantPermission\Console\CacheResetCommand;
use BuildWithSmk\TenantPermission\Console\InstallCommand;
use BuildWithSmk\TenantPermission\Console\SuperAdminCommand;
use BuildWithSmk\TenantPermission\Http\UiHelper;
use BuildWithSmk\TenantPermission\Middleware\PermissionMiddleware;
use BuildWithSmk\TenantPermission\Middleware\RoleMiddleware;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Package migrations, in run order, keyed by their name suffix.
     * The suffix is how an already-published copy is recognised.
     *
     * @var array<int, string>
     */
    protected const MIGRATIONS = [
        'create_permission_tables',
        'add_is_admin_to_roles_table',
        'add_description_to_roles_table',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/permission.php', 'permission');

        $this->app->scoped(PermissionRegistrar::class, function ($app): PermissionRegistrar {
            $store = config('permission.cache.store');

            return new PermissionRegistrar($app['cache']->store($store));
        });

        $this->app->singleton(UiHelper::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerMigrations();
        $this->registerMiddleware();
        $this->registerGate();
        $this->registerUi();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CacheResetCommand::class,
                SuperAdminCommand::class,
            ]);

            $this->registerInstallHint();
        }
    }

    /**
     * Composer runs `package:discover` after every require/update. Until the
     * config is published, append a one-line pointer to the install command
     * so the next step is visible right in the composer output.
     */
    protected function registerInstallHint(): void
    {
        if (file_exists(config_path('permission.php'))) {
            return;
        }

        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            if ($event->command !== 'package:discover' || $event->exitCode !== 0) {
                return;
            }

            $event->output->writeln('');
            $event->output->writeln('  <fg=yellow;options=bold>buildwithsmk/laravel-tenant-permission:</> run <fg=green>php artisan permission:install</> to finish setup.');
            $event->output->writeln('');
        });
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/permission.php' => config_path('permission.php'),
        ], 'permission-config');

        $this->publishes($this->migrationPublishMap(), 'permission-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/permission'),
        ], 'permission-views');
    }

    /**
     * Publish each migration once: reuse an existing published file's path
     * (so vendor:publish skips it) or give it a fresh, ordered timestamp.
     *
     * @return array<string, string>
     */
    protected function migrationPublishMap(): array
    {
        $map = [];
        $timestamp = time();

        foreach (self::MIGRATIONS as $index => $suffix) {
            $source = $this->packageMigrationPath($suffix);

            $map[$source] = $this->publishedMigrationPath($suffix)
                ?? database_path('migrations/'.date('Y_m_d_His', $timestamp + $index).'_'.$suffix.'.php');
        }

        return $map;
    }

    /**
     * Auto-load every package migration the app has not published itself,
     * so `php artisan migrate` just works without publishing anything.
     */
    protected function registerMigrations(): void
    {
        $paths = collect(self::MIGRATIONS)
            ->reject(fn (string $suffix): bool => $this->publishedMigrationPath($suffix) !== null)
            ->map(fn (string $suffix): string => $this->packageMigrationPath($suffix))
            ->values()
            ->all();

        if ($paths !== []) {
            $this->loadMigrationsFrom($paths);
        }
    }

    protected function packageMigrationPath(string $suffix): string
    {
        return glob(__DIR__.'/../database/migrations/*_'.$suffix.'.php')[0];
    }

    protected function publishedMigrationPath(string $suffix): ?string
    {
        return glob(database_path('migrations/*_'.$suffix.'.php'))[0] ?? null;
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $aliases = config('permission.middleware_aliases', []);

        $router->aliasMiddleware($aliases['permission'] ?? 'permission', PermissionMiddleware::class);
        $router->aliasMiddleware($aliases['role'] ?? 'tenant_role', RoleMiddleware::class);
    }

    /**
     * Route @can, $user->can() and authorize() through the package.
     * Admins pass every ability. A missing permission returns null, so
     * your own policies still get a say.
     */
    protected function registerGate(): void
    {
        if (! config('permission.register_gate', true)) {
            return;
        }

        Gate::before(function ($user, string $ability): ?bool {
            if (! method_exists($user, 'hasPermissionTo')) {
                return null;
            }

            if ($user->isSuperAdmin() || $user->isTenantAdmin()) {
                return true;
            }

            return $user->hasPermissionTo($ability) ? true : null;
        });
    }

    protected function registerUi(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'permission');

        View::composer('permission::*', fn ($view) => $view->with('pm', $this->app->make(UiHelper::class)));

        if (! config('permission.ui.enabled', false)) {
            return;
        }

        Route::middleware(config('permission.ui.middleware', ['web', 'auth']))
            ->prefix(config('permission.ui.prefix', 'access-control'))
            ->name(config('permission.ui.route_name_prefix', 'permission.ui.'))
            ->group(__DIR__.'/../routes/web.php');
    }
}
