<?php

namespace BuildWithSmk\TenantPermission\Tests\Fixtures;

use BuildWithSmk\TenantPermission\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * Branch-owned data with a custom column, like cims' office_id.
 */
class Patient extends Model
{
    use BelongsToBranch;

    protected $table = 'patients';

    protected $guarded = [];

    protected string $branchColumn = 'office_id';
}
