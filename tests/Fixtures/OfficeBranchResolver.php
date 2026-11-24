<?php

namespace BuildWithSmk\TenantPermission\Tests\Fixtures;

use BuildWithSmk\TenantPermission\Contracts\BranchResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Branch 1 is HQ with branches 2, 3 and 4 below it. Users of HQ can access
 * all four; everyone else only their own branch.
 */
class OfficeBranchResolver implements BranchResolver
{
    public const HQ = 1;

    public const MAIN = 2;

    public const GULSHAN = 3;

    public const BANANI = 4;

    public function defaultBranchId(Model $user): ?int
    {
        return $user->branch_id === null ? null : (int) $user->branch_id;
    }

    public function accessibleBranchIds(Model $user): array
    {
        $home = $this->defaultBranchId($user);

        return match ($home) {
            null => [],
            self::HQ => [self::HQ, self::MAIN, self::GULSHAN, self::BANANI],
            default => [$home],
        };
    }
}
