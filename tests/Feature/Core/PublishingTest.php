<?php

use BuildWithSmk\TenantPermission\PermissionServiceProvider;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('publishes the views for redesign', function () {
    $paths = ServiceProvider::pathsToPublish(PermissionServiceProvider::class, 'permission-views');

    expect(array_values($paths))->toBe([resource_path('views/vendor/permission')]);
});

it('points to the install command after package discovery', function () {
    $output = new BufferedOutput;

    event(new CommandFinished('package:discover', new ArrayInput([]), $output, 0));
    expect($output->fetch())->toContain('php artisan permission:install');

    event(new CommandFinished('migrate', new ArrayInput([]), $output, 0));
    expect($output->fetch())->toBe('');
});

it('publishes every migration in run order', function () {
    $destinations = array_values(ServiceProvider::pathsToPublish(PermissionServiceProvider::class, 'permission-migrations'));
    $names = array_map('basename', $destinations);
    $sorted = $names;
    sort($sorted);

    expect($names)->toHaveCount(4)
        ->and($sorted)->toBe($names)
        ->and($names[0])->toEndWith('_create_permission_tables.php')
        ->and($names[3])->toEndWith('_add_branch_id_to_permission_pivots.php');
});
