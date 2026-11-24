<?php

use BuildWithSmk\TenantPermission\PermissionRegistrar;
use BuildWithSmk\TenantPermission\Tests\Fixtures\OfficeBranchResolver as Office;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'resolve_branch'])->get('/_branch', fn (PermissionRegistrar $registrar) => [
        'current' => $registrar->getCurrentBranchId(),
        'accessible' => $registrar->getAccessibleBranchIds(),
    ]);

    $this->key = config('permission.branches.session_key');
});

it('starts an HQ user in their home branch and lets them pick All branches', function () {
    $hq = $this->makeUser(['branch_id' => Office::HQ]);

    $this->actingAs($hq)->get('/_branch')
        ->assertJson(['current' => Office::HQ, 'accessible' => [1, 2, 3, 4]]);

    $this->actingAs($hq)->withSession([$this->key => 0])->get('/_branch')
        ->assertJson(['current' => 0]);
});

it('can start HQ users in All branches', function () {
    config()->set('permission.branches.default_all', true);

    $this->actingAs($this->makeUser(['branch_id' => Office::HQ]))->get('/_branch')->assertJson(['current' => 0]);
    $this->actingAs($this->makeUser(['branch_id' => Office::MAIN]))->get('/_branch')->assertJson(['current' => Office::MAIN]);
});

it('pins a branch user to their branch whatever the session says', function () {
    $user = $this->makeUser(['branch_id' => Office::GULSHAN]);

    foreach ([0, Office::BANANI, 999] as $requested) {
        $this->actingAs($user)->withSession([$this->key => $requested])->get('/_branch')
            ->assertJson(['current' => Office::GULSHAN, 'accessible' => [Office::GULSHAN]]);
    }
});

it('switches the branch through the switch route', function () {
    $hq = $this->makeUser(['branch_id' => Office::HQ]);

    $this->actingAs($hq)->postJson(route('permission.branch.switch'), ['branch_id' => Office::BANANI])
        ->assertOk()
        ->assertJson(['branch_id' => Office::BANANI])
        ->assertSessionHas($this->key, Office::BANANI);

    $this->actingAs($hq)->postJson(route('permission.branch.switch'), ['branch_id' => 0])
        ->assertSessionHas($this->key, 0);
});

it('refuses a switch to a branch the user cannot access', function () {
    $user = $this->makeUser(['branch_id' => Office::GULSHAN]);

    $this->actingAs($user)->postJson(route('permission.branch.switch'), ['branch_id' => Office::BANANI])->assertForbidden();
    $this->actingAs($user)->postJson(route('permission.branch.switch'), ['branch_id' => 0])->assertForbidden();
    $this->actingAs($user)->postJson(route('permission.branch.switch'), ['branch_id' => Office::GULSHAN])->assertOk();
});
