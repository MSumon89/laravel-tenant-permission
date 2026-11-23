<?php

use BuildWithSmk\TenantPermission\Models\Permission;
use BuildWithSmk\TenantPermission\Models\Role;
use BuildWithSmk\TenantPermission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    foreach (['posts.view', 'posts.edit', 'posts.delete'] as $name) {
        Permission::findOrCreate($name);
    }
});

it('grants permissions through roles', function () {
    Role::findOrCreate('Editor')->givePermissionTo('posts.view', 'posts.edit');
    $user = $this->makeUser()->assignRole('Editor');

    expect($user->hasRole('Editor'))->toBeTrue()
        ->and($user->hasPermissionTo('posts.edit'))->toBeTrue()
        ->and($user->hasPermissionTo('posts.delete'))->toBeFalse()
        ->and($user->can('posts.edit'))->toBeTrue()
        ->and($user->can('posts.delete'))->toBeFalse();
});

it('lets a direct deny beat a role grant and a direct grant add one', function () {
    Role::findOrCreate('Editor')->givePermissionTo('posts.view', 'posts.edit');
    $user = $this->makeUser()->assignRole('Editor');

    $user->denyPermissionTo('posts.edit')->givePermissionTo('posts.delete');

    expect($user->getAllPermissions())->toEqualCanonicalizing(['posts.view', 'posts.delete']);

    $user->clearPermissionOverride('posts.edit');

    expect($user->hasPermissionTo('posts.edit'))->toBeTrue();
});

it('ignores tenant ids on users while tenancy is disabled', function () {
    Role::findOrCreate('Editor')->givePermissionTo('posts.edit');
    $user = $this->makeUser(['tenant_id' => 5])->assignRole('Editor');

    expect($user->resolveTenantId())->toBe(0)
        ->and($user->hasPermissionTo('posts.edit'))->toBeTrue();
});

it('lets the super admin pass every gate ability', function () {
    $root = $this->makeSuperAdmin();

    expect(Gate::forUser($root)->allows('posts.delete'))->toBeTrue()
        ->and(Gate::forUser($root)->allows('some-policy-ability'))->toBeTrue();
});

it('gives holders of an is_admin role every permission', function () {
    $role = Role::findOrCreate('Admin');
    $role->update(['is_admin' => true]);

    $user = $this->makeUser()->assignRole('Admin');

    expect($user->getAllPermissions())->toEqualCanonicalizing(['posts.view', 'posts.edit', 'posts.delete']);
});

it('sees role permission changes immediately despite the cache', function () {
    $role = Role::findOrCreate('Editor');
    $user = $this->makeUser()->assignRole('Editor');

    expect($user->hasPermissionTo('posts.edit'))->toBeFalse();

    $role->givePermissionTo('posts.edit');
    expect($user->hasPermissionTo('posts.edit'))->toBeTrue();

    $role->revokePermissionTo('posts.edit');
    expect($user->hasPermissionTo('posts.edit'))->toBeFalse();
});

it('refreshes role checks right after assigning and removing a role', function () {
    Role::findOrCreate('Editor');
    $user = $this->makeUser();

    expect($user->hasRole('Editor'))->toBeFalse();

    $user->assignRole('Editor');
    expect($user->hasRole('Editor'))->toBeTrue();

    $user->removeRole('Editor');
    expect($user->hasRole('Editor'))->toBeFalse();
});

it('never flushes the rest of the application cache', function () {
    cache()->put('unrelated', 'still here');

    app(PermissionRegistrar::class)->forgetAll();

    expect(cache()->get('unrelated'))->toBe('still here');
});

it('removes pivot rows when a user is deleted', function () {
    Role::findOrCreate('Editor');
    $user = $this->makeUser()->assignRole('Editor')->givePermissionTo('posts.view');

    $user->delete();

    expect(DB::table('model_has_roles')->count())->toBe(0)
        ->and(DB::table('model_has_permissions')->count())->toBe(0);
});

it('does not register UI routes unless enabled', function () {
    expect(Route::has('permission.ui.roles.index'))->toBeFalse();
});
