<?php

namespace BuildWithSmk\TenantPermission\Console;

use BuildWithSmk\TenantPermission\PermissionRegistrar;
use Illuminate\Console\Command;

class SuperAdminCommand extends Command
{
    protected $signature = 'permission:super-admin {user : The user id or email address}';

    protected $description = 'Give a user the global Super Admin role (creates the role if needed)';

    public function handle(PermissionRegistrar $registrar): int
    {
        $userClass = $registrar->userClass();
        $identifier = (string) $this->argument('user');

        $user = ctype_digit($identifier)
            ? $userClass::query()->find((int) $identifier)
            : $userClass::query()->where('email', $identifier)->first();

        if (! $user) {
            $this->components->error("No user found for [{$identifier}].");

            return self::FAILURE;
        }

        if (! method_exists($user, 'assignRole')) {
            $this->components->error($userClass.' does not use the HasRolesAndPermissions trait.');

            return self::FAILURE;
        }

        $role = $registrar->roleClass()::findOrCreate($registrar->superAdminRoleName(), $registrar->globalTenantId());
        $role->forceFill(['is_system' => true])->save();

        $user->assignRole($role);

        $this->components->info("User #{$user->getKey()} is now {$role->name}.");

        return self::SUCCESS;
    }
}
