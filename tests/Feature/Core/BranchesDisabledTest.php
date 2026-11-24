<?php

use BuildWithSmk\TenantPermission\Models\Permission;
use BuildWithSmk\TenantPermission\Models\Role;
use BuildWithSmk\TenantPermission\PermissionRegistrar;

it('ignores branch-only assignments while branches are disabled', function () {
    Permission::findOrCreate('posts.edit');
    Role::findOrCreate('Editor')->givePermissionTo('posts.edit');

    $user = $this->makeUser(['branch_id' => 3])->assignRole('Editor', branchId: 3);
    app(PermissionRegistrar::class)->setCurrentBranchId(3);

    expect($user->resolveBranchId())->toBe(0)
        ->and($user->hasPermissionTo('posts.edit'))->toBeFalse()
        ->and($user->hasPermissionTo('posts.edit', branchId: 3))->toBeTrue();
});

it('stores assignments made without a branch under branch 0', function () {
    Role::findOrCreate('Editor');
    $user = $this->makeUser()->assignRole('Editor');

    expect((int) $user->roles->first()->pivot->branch_id)->toBe(0);
});
