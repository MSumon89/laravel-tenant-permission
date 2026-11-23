<?php

use Illuminate\Support\ServiceProvider;
use Smk\Permission\PermissionServiceProvider;

it('publishes the views for redesign', function () {
    $paths = ServiceProvider::pathsToPublish(PermissionServiceProvider::class, 'permission-views');

    expect(array_values($paths))->toBe([resource_path('views/vendor/permission')]);
});

it('publishes every migration in run order', function () {
    $destinations = array_values(ServiceProvider::pathsToPublish(PermissionServiceProvider::class, 'permission-migrations'));
    $names = array_map('basename', $destinations);
    $sorted = $names;
    sort($sorted);

    expect($names)->toHaveCount(3)
        ->and($sorted)->toBe($names)
        ->and($names[0])->toEndWith('_create_permission_tables.php');
});
