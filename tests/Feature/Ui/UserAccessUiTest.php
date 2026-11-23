<?php

use BuildWithSmk\TenantPermission\Models\Permission;
use BuildWithSmk\TenantPermission\Models\Role;

beforeEach(function () {
    foreach (['access-control.manage', 'posts.view', 'posts.edit', 'posts.delete'] as $name) {
        Permission::findOrCreate($name);
    }
});

it('assigns roles and saves overrides', function () {
    $editor = Role::findOrCreate('Editor')->givePermissionTo('posts.view', 'posts.edit');
    $user = $this->makeUser();
    $root = $this->makeSuperAdmin();

    $this->actingAs($root)->get("/access-control/users/{$user->id}")->assertOk()->assertSee('posts.edit');

    $this->actingAs($root)->put("/access-control/users/{$user->id}", [
        'roles' => [$editor->id],
        'overrides' => ['posts.edit' => 'deny', 'posts.delete' => 'grant', 'posts.view' => 'inherit'],
    ])->assertRedirect("/access-control/users/{$user->id}");

    $user->refresh();

    expect($user->hasRole('Editor'))->toBeTrue()
        ->and($user->getAllPermissions())->toEqualCanonicalizing(['posts.view', 'posts.delete']);
});

it('lists users', function () {
    $this->makeUser(['name' => 'Jane Visible']);

    $this->actingAs($this->makeSuperAdmin())->get('/access-control/users')->assertOk()->assertSee('Jane Visible');
});

it('stops an admin from removing their own admin role', function () {
    Role::findOrCreate('Admin')->update(['is_admin' => true]);
    $admin = $this->makeUser()->assignRole('Admin');

    $this->actingAs($admin)
        ->put("/access-control/users/{$admin->id}", ['roles' => []])
        ->assertSessionHasErrors('roles');

    expect($admin->fresh()->hasRole('Admin'))->toBeTrue();
});

it('hides users of other tenants and the catalog editor from tenant admins', function () {
    config()->set('permission.tenancy.enabled', true);

    Role::findOrCreate('Owner', 1)->update(['is_admin' => true]);
    $owner = $this->makeUser(['tenant_id' => 1])->assignRole('Owner');
    $stranger = $this->makeUser(['tenant_id' => 2, 'name' => 'Other Tenant Person']);

    $this->actingAs($owner)->get('/access-control/users')->assertOk()->assertDontSee('Other Tenant Person');
    $this->actingAs($owner)->get("/access-control/users/{$stranger->id}")->assertNotFound();
    $this->actingAs($owner)->get('/access-control/permissions')->assertOk();
    $this->actingAs($owner)->get('/access-control/permissions/create')->assertForbidden();
});

it('lets a super admin manage the catalog', function () {
    $root = $this->makeSuperAdmin();

    $this->actingAs($root)
        ->post('/access-control/permissions', ['name' => 'invoices.refund', 'display_name' => 'Refund invoices'])
        ->assertRedirect('/access-control/permissions');

    expect(Permission::findByName('invoices.refund')->module)->toBe('invoices');

    $this->actingAs($root)
        ->post('/access-control/permissions', ['name' => 'bad name!'])
        ->assertSessionHasErrors('name');
});
