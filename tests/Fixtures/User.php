<?php

namespace BuildWithSmk\TenantPermission\Tests\Fixtures;

use BuildWithSmk\TenantPermission\Traits\HasRolesAndPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasRolesAndPermissions;

    protected $table = 'users';

    protected $guarded = [];
}
