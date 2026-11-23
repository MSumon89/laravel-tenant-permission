<?php

namespace Smk\Permission\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Smk\Permission\Models\Role;
use Smk\Permission\PermissionServiceProvider;
use Smk\Permission\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [PermissionServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeUser(array $attributes = []): User
    {
        static $sequence = 0;
        $sequence++;

        return User::query()->create($attributes + [
            'name' => "User {$sequence}",
            'email' => "user{$sequence}@example.test",
        ]);
    }

    protected function makeSuperAdmin(): User
    {
        $user = $this->makeUser(['name' => 'Root']);
        $user->assignRole(Role::findOrCreate(config('permission.super_admin_role'), 0));

        return $user;
    }
}
