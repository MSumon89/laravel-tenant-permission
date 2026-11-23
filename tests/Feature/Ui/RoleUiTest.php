<?php

use BuildWithSmk\TenantPermission\Models\Permission;
use BuildWithSmk\TenantPermission\Models\Role;

beforeEach(function () {
    foreach (['access-control.manage', 'posts.view', 'posts.edit', 'posts.delete'] as $name) {
        Permission::findOrCreate($name);
    }
});

it('lets a super admin list and create roles', function () {
    $root = $this->makeSuperAdmin();

    $this->actingAs($root)->get('/access-control/roles')->assertOk()->assertSee('Super Admin');

    $this->actingAs($root)
        ->post('/access-control/roles', ['name' => 'Editor', 'permissions' => ['posts.view', 'posts.edit']])
        ->assertRedirect('/access-control/roles');

    expect(Role::findByName('Editor')->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['posts.view', 'posts.edit']);
});

it('renders the role form', function () {
    $this->actingAs($this->makeSuperAdmin())
        ->get('/access-control/roles/create')
        ->assertOk()
        ->assertSee('posts.edit');
});

it('forbids users without the manage permission', function () {
    $this->actingAs($this->makeUser())->get('/access-control/roles')->assertForbidden();
});

it('stops a manager from granting permissions they do not hold', function () {
    Role::findOrCreate('Manager')->givePermissionTo('access-control.manage', 'posts.view');
    $manager = $this->makeUser()->assignRole('Manager');

    $this->actingAs($manager)
        ->post('/access-control/roles', ['name' => 'Sneaky', 'permissions' => ['posts.delete']])
        ->assertForbidden();

    $this->actingAs($manager)
        ->post('/access-control/roles', ['name' => 'Viewer', 'permissions' => ['posts.view']])
        ->assertRedirect();

    expect(Role::query()->where('name', 'Viewer')->exists())->toBeTrue();
});

it('hides the super admin role from non super admins', function () {
    Role::findOrCreate('Manager')->update(['is_admin' => true]);
    $admin = $this->makeUser()->assignRole('Manager');
    $this->makeSuperAdmin();

    $superRole = Role::findByName('Super Admin');

    $this->actingAs($admin)->get('/access-control/roles')->assertOk()->assertDontSee('Super Admin');
    $this->actingAs($admin)->get("/access-control/roles/{$superRole->id}/edit")->assertNotFound();
});

it('refuses to delete system roles', function () {
    $role = Role::findOrCreate('Owner');
    $role->update(['is_system' => true]);

    $this->actingAs($this->makeSuperAdmin())
        ->delete("/access-control/roles/{$role->id}")
        ->assertForbidden();
});

it('isolates tenants from each other', function () {
    config()->set('permission.tenancy.enabled', true);

    Role::findOrCreate('Owner', 1)->update(['is_admin' => true]);
    $otherTenantRole = Role::findOrCreate('Staff', 2);
    $owner = $this->makeUser(['tenant_id' => 1])->assignRole('Owner');

    $this->actingAs($owner)->get("/access-control/roles/{$otherTenantRole->id}/edit")->assertNotFound();

    $this->actingAs($owner)->post('/access-control/roles', ['name' => 'Staff'])->assertRedirect();
    expect(Role::query()->where('name', 'Staff')->where('tenant_id', 1)->exists())->toBeTrue();
});
