<?php

use BuildWithSmk\TenantPermission\Models\Permission;
use BuildWithSmk\TenantPermission\Models\Role;
use BuildWithSmk\TenantPermission\PermissionRegistrar;
use BuildWithSmk\TenantPermission\Tests\Fixtures\ShopUser;

beforeEach(function () {
    config()->set('permission.tenancy.enabled', true);

    Permission::findOrCreate('rooms.edit');
    Permission::findOrCreate('reports.view');
});

it('keeps same-named roles separate per tenant', function () {
    Role::findOrCreate('Manager', 1)->givePermissionTo('rooms.edit');
    Role::findOrCreate('Manager', 2);

    $alice = $this->makeUser(['tenant_id' => 1])->assignRole('Manager');
    $bob = $this->makeUser(['tenant_id' => 2])->assignRole('Manager');

    expect($alice->hasPermissionTo('rooms.edit'))->toBeTrue()
        ->and($bob->hasPermissionTo('rooms.edit'))->toBeFalse()
        ->and($alice->roles->first()->tenant_id)->toBe(1)
        ->and($bob->roles->first()->tenant_id)->toBe(2);
});

it('checks a user who works in several tenants against the pinned tenant', function () {
    $user = $this->makeUser();
    $user->assignRole(Role::findOrCreate('Manager', 1)->givePermissionTo('rooms.edit'));
    $user->assignRole(Role::findOrCreate('Staff', 2));

    $registrar = app(PermissionRegistrar::class);

    $registrar->setCurrentTenantId(1);
    expect($user->hasPermissionTo('rooms.edit'))->toBeTrue();

    $registrar->setCurrentTenantId(2);
    expect($user->hasPermissionTo('rooms.edit'))->toBeFalse();
});

it('applies global roles in every tenant and sees changes to them at once', function () {
    $auditor = Role::findOrCreate('Auditor', 0);
    $user = $this->makeUser(['tenant_id' => 7])->assignRole($auditor);

    expect($user->hasRole('Auditor'))->toBeTrue()
        ->and($user->hasPermissionTo('reports.view'))->toBeFalse();

    $auditor->givePermissionTo('reports.view');

    expect($user->hasPermissionTo('reports.view'))->toBeTrue();
});

it('scopes per-user overrides to one tenant', function () {
    $user = $this->makeUser(['tenant_id' => 1]);
    $user->givePermissionTo('reports.view', 1);

    expect($user->hasPermissionTo('reports.view', 1))->toBeTrue()
        ->and($user->hasPermissionTo('reports.view', 2))->toBeFalse();
});

it('limits an is_admin role to its own tenant', function () {
    $owner = Role::findOrCreate('Owner', 1);
    $owner->update(['is_admin' => true]);
    $user = $this->makeUser(['tenant_id' => 1])->assignRole('Owner');

    expect($user->isTenantAdmin())->toBeTrue()
        ->and($user->hasPermissionTo('rooms.edit'))->toBeTrue()
        ->and($user->hasPermissionTo('rooms.edit', 2))->toBeFalse();
});

it('honours the BelongsToTenant contract', function () {
    Role::findOrCreate('Manager', 9)->givePermissionTo('rooms.edit');

    $user = ShopUser::query()->create(['name' => 'Shop', 'email' => 'shop@example.test', 'shop_id' => 9]);
    $user->assignRole('Manager');

    expect($user->resolveTenantId())->toBe(9)
        ->and($user->hasPermissionTo('rooms.edit'))->toBeTrue();
});
