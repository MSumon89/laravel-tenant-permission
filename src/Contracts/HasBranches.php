<?php

namespace BuildWithSmk\TenantPermission\Contracts;

/**
 * Implement on your User model to describe its branches without writing a
 * full BranchResolver. Used by the DefaultBranchResolver.
 */
interface HasBranches
{
    /**
     * The user's home branch, or null.
     */
    public function getBranchId(): ?int;

    /**
     * Every branch the user may see or select.
     *
     * @return array<int, int>
     */
    public function getAccessibleBranchIds(): array;
}
