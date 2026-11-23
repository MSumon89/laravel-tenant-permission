<?php

use BuildWithSmk\TenantPermission\Models\Permission;
use BuildWithSmk\TenantPermission\Models\Role;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Permission::findOrCreate('posts.edit');
    Permission::findOrCreate('posts.delete');

    Route::middleware(['web', 'permission:posts.edit|posts.delete'])->get('/_test/permission', fn () => 'ok');
    Route::middleware(['web', 'tenant_role:Editor'])->get('/_test/role', fn () => 'ok');
});

it('rejects guests with 401', function () {
    $this->getJson('/_test/permission')->assertUnauthorized();
});

it('rejects users without any of the permissions', function () {
    $this->actingAs($this->makeUser())->get('/_test/permission')->assertForbidden();
});

it('allows users with any of the listed permissions', function () {
    $user = $this->makeUser()->givePermissionTo('posts.delete');

    $this->actingAs($user)->get('/_test/permission')->assertOk();
});

it('checks roles and lets the super admin through', function () {
    Role::findOrCreate('Editor');

    $this->actingAs($this->makeUser())->get('/_test/role')->assertForbidden();
    $this->actingAs($this->makeUser()->assignRole('Editor'))->get('/_test/role')->assertOk();
    $this->actingAs($this->makeSuperAdmin())->get('/_test/role')->assertOk();
});
