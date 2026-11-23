<?php

namespace BuildWithSmk\TenantPermission\Tests\Fixtures;

use BuildWithSmk\TenantPermission\Contracts\BelongsToTenant;

/**
 * A user whose tenant lives in a differently named column.
 */
class ShopUser extends User implements BelongsToTenant
{
    public function getTenantId(): ?int
    {
        return $this->shop_id;
    }
}
