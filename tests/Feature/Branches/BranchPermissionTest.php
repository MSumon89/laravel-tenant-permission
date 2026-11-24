<?php

use BuildWithSmk\TenantPermission\Models\Permission;
use BuildWithSmk\TenantPermission\Models\Role;
use BuildWithSmk\TenantPermission\PermissionRegistrar;
use BuildWithSmk\TenantPermission\Tests\Fixtures\OfficeBranchResolver as Office;

beforeEach(function () {
    Permission::findOrCreate('patients.view');
    Permission::findOrCreate('patients.edit');
    Permission::findOrCreate('reports.view');

    $this->registrar = app(PermissionRegistrar::class);
});

it('applies a role assigned for every branch in each branch', function () {
    Role::findOrCreate('Doctor')->givePermissionTo('patients.view');
    $user = $this->makeUser(['branch_id' => Office::HQ])->assignRole('Doctor');

    foreach ([Office::MAIN, Office::GULSHAN, PermissionRegistrar::ALL_BRANCHES] as $branch) {
        expect($user->hasPermissionTo('patients.view', branchId: $branch))->toBeTrue();
    }
});

it('applies a branch-only role in that branch only', function () {
    Role::findOrCreate('Manager')->givePermissionTo('patients.edit');
    $user = $this->makeUser(['branch_id' => Office::HQ])->assignRole('Manager', branchId: Office::GULSHAN);

    $this->registrar->setCurrentBranchId(Office::GULSHAN);
    expect($user->hasPermissionTo('patients.edit'))->toBeTrue()
        ->and($user->hasRole('Manager'))->toBeTrue();

    $this->registrar->setCurrentBranchId(Office::BANANI);
    expect($user->hasPermissionTo('patients.edit'))->toBeFalse()
        ->and($user->hasRole('Manager'))->toBeFalse();
});

it('ignores branch-only roles in the All branches view', function () {
    Role::findOrCreate('Manager')->givePermissionTo('patients.edit');
    Role::findOrCreate('Auditor')->givePermissionTo('reports.view');

    $user = $this->makeUser(['branch_id' => Office::HQ])
        ->assignRole('Manager', branchId: Office::GULSHAN)
        ->assignRole('Auditor');

    $this->registrar->setCurrentBranchId(PermissionRegistrar::ALL_BRANCHES);

    expect($user->hasPermissionTo('patients.edit'))->toBeFalse()
        ->and($user->hasPermissionTo('reports.view'))->toBeTrue();
});

it('falls back to the home branch when no branch is pinned', function () {
    Role::findOrCreate('Manager')->givePermissionTo('patients.edit');
    $user = $this->makeUser(['branch_id' => Office::GULSHAN])->assignRole('Manager', branchId: Office::GULSHAN);

    expect($this->registrar->getCurrentBranchId())->toBeNull()
        ->and($user->resolveBranchId())->toBe(Office::GULSHAN)
        ->and($user->hasPermissionTo('patients.edit'))->toBeTrue();
});

it('keeps the same role in two branches as separate assignments', function () {
    $manager = Role::findOrCreate('Manager')->givePermissionTo('patients.edit');
    $user = $this->makeUser()
        ->assignRole($manager, branchId: Office::GULSHAN)
        ->assignRole($manager, branchId: Office::BANANI)
        ->assignRole($manager, branchId: Office::BANANI); // idempotent

    expect($user->roles()->count())->toBe(2)
        ->and($user->getRoleNames(branchId: Office::GULSHAN)->all())->toBe(['Manager']);

    $user->removeRole($manager, branchId: Office::GULSHAN);

    expect($user->hasRole('Manager', branchId: Office::GULSHAN))->toBeFalse()
        ->and($user->hasRole('Manager', branchId: Office::BANANI))->toBeTrue();
});

it('syncs roles of one branch without touching other branches', function () {
    Role::findOrCreate('Doctor');
    Role::findOrCreate('Manager');
    Role::findOrCreate('Cashier');

    $user = $this->makeUser()
        ->assignRole('Doctor')
        ->assignRole('Manager', branchId: Office::GULSHAN);

    $user->syncRoles(['Cashier'], branchId: Office::BANANI);
    $user->syncRoles(['Doctor', 'Cashier']);

    expect($user->getRoleNames(branchId: Office::GULSHAN)->sort()->values()->all())->toBe(['Cashier', 'Doctor', 'Manager'])
        ->and($user->getRoleNames(branchId: Office::BANANI)->sort()->values()->all())->toBe(['Cashier', 'Doctor'])
        ->and($user->getRoleNames(branchId: 0)->sort()->values()->all())->toBe(['Cashier', 'Doctor']);
});

it('limits an is_admin role assigned in one branch to that branch', function () {
    Role::findOrCreate('Branch Admin')->update(['is_admin' => true]);
    $user = $this->makeUser()->assignRole('Branch Admin', branchId: Office::GULSHAN);

    expect($user->isTenantAdmin(branchId: Office::GULSHAN))->toBeTrue()
        ->and($user->hasPermissionTo('patients.edit', branchId: Office::GULSHAN))->toBeTrue()
        ->and($user->isTenantAdmin(branchId: Office::BANANI))->toBeFalse()
        ->and($user->hasPermissionTo('patients.edit', branchId: Office::BANANI))->toBeFalse()
        ->and($user->isTenantAdmin(branchId: 0))->toBeFalse();
});

it('scopes overrides to a branch and lets a deny in either scope win', function () {
    Role::findOrCreate('Doctor')->givePermissionTo('patients.view', 'patients.edit');
    $user = $this->makeUser()->assignRole('Doctor');

    $user->denyPermissionTo('patients.edit', branchId: Office::BANANI);
    $user->givePermissionTo('reports.view', branchId: Office::GULSHAN);

    expect($user->hasPermissionTo('patients.edit', branchId: Office::GULSHAN))->toBeTrue()
        ->and($user->hasPermissionTo('patients.edit', branchId: Office::BANANI))->toBeFalse()
        ->and($user->hasPermissionTo('reports.view', branchId: Office::GULSHAN))->toBeTrue()
        ->and($user->hasPermissionTo('reports.view', branchId: Office::BANANI))->toBeFalse()
        ->and($user->getPermissionOverrides())->toBe([])
        ->and($user->getPermissionOverrides(branchId: Office::BANANI))->toBe(['patients.edit' => 'deny']);

    // A deny for every branch beats a grant for one branch.
    $user->denyPermissionTo('reports.view');
    expect($user->hasPermissionTo('reports.view', branchId: Office::GULSHAN))->toBeFalse();
});

it('caches permissions per branch', function () {
    Role::findOrCreate('Manager')->givePermissionTo('patients.edit');
    $user = $this->makeUser()->assignRole('Manager', branchId: Office::GULSHAN);

    $this->registrar->setCurrentBranchId(Office::GULSHAN);
    expect($user->getAllPermissions())->toBe(['patients.edit']);

    $this->registrar->setCurrentBranchId(Office::BANANI);
    expect($user->getAllPermissions())->toBe([]);
});

it('combines tenant and branch scopes when tenancy is on', function () {
    config()->set('permission.tenancy.enabled', true);

    Role::findOrCreate('Manager', 1)->givePermissionTo('patients.edit');
    Role::findOrCreate('Manager', 2)->givePermissionTo('patients.edit');
    $user = $this->makeUser(['tenant_id' => 1, 'branch_id' => Office::GULSHAN])
        ->assignRole('Manager', branchId: Office::GULSHAN);

    expect($user->hasPermissionTo('patients.edit'))->toBeTrue()
        ->and($user->hasPermissionTo('patients.edit', tenantId: 2))->toBeFalse()
        ->and($user->hasPermissionTo('patients.edit', branchId: Office::BANANI))->toBeFalse();
});

it('routes the Gate and middleware through the current branch', function () {
    Role::findOrCreate('Manager')->givePermissionTo('patients.edit');
    $user = $this->makeUser()->assignRole('Manager', branchId: Office::GULSHAN);

    $this->registrar->setCurrentBranchId(Office::GULSHAN);
    expect($user->can('patients.edit'))->toBeTrue();

    $this->registrar->setCurrentBranchId(Office::BANANI);
    expect($user->can('patients.edit'))->toBeFalse();
});
