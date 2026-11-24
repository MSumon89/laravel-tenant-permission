<?php

namespace BuildWithSmk\TenantPermission\Branches;

use BuildWithSmk\TenantPermission\Contracts\BranchResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Uses the HasBranches methods on the user when present. Otherwise the home
 * branch is the attribute named by config('permission.branches.user_column'),
 * and that is the only branch the user can access.
 */
class DefaultBranchResolver implements BranchResolver
{
    public function defaultBranchId(Model $user): ?int
    {
        if (method_exists($user, 'getBranchId')) {
            $branchId = $user->getBranchId();
        } else {
            $column = config('permission.branches.user_column', 'branch_id');
            $branchId = $column ? $user->getAttribute($column) : null;
        }

        return $branchId === null ? null : (int) $branchId;
    }

    public function accessibleBranchIds(Model $user): array
    {
        if (method_exists($user, 'getAccessibleBranchIds')) {
            return array_values(array_unique(array_map('intval', $user->getAccessibleBranchIds())));
        }

        $branchId = $this->defaultBranchId($user);

        return $branchId === null ? [] : [$branchId];
    }
}
