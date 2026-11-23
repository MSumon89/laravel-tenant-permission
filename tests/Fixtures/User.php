<?php

namespace Smk\Permission\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Smk\Permission\Traits\HasRolesAndPermissions;

class User extends Authenticatable
{
    use HasRolesAndPermissions;

    protected $table = 'users';

    protected $guarded = [];
}
