<?php

namespace Smk\Permission\Contracts;

/**
 * Implement on your User model when its tenant is not a plain `tenant_id`
 * column (shop_id, vendor_id, a relation, ...). Only used when tenancy is
 * enabled.
 */
interface BelongsToTenant
{
    /**
     * The tenant this user belongs to, or null for platform users who
     * should be checked in the global scope.
     */
    public function getTenantId(): ?int;
}
