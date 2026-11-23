<?php

namespace Smk\Permission\Tests\Fixtures;

use Smk\Permission\Contracts\BelongsToTenant;

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
