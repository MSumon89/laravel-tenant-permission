<?php

namespace BuildWithSmk\TenantPermission\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Tells the package about the app's own branches (offices, clinics,
 * outlets, ...) when config('permission.branches.enabled') is true.
 *
 * The package has no branches table. Branch ids are whatever ids your app
 * uses, e.g. offices.id. Bind your implementation via
 * config('permission.branches.resolver').
 */
interface BranchResolver
{
    /**
     * The user's home branch, used when no branch has been selected.
     * Return null if the user has no home branch.
     */
    public function defaultBranchId(Model $user): ?int;

    /**
     * Every branch the user may see or select. An HQ user returns all the
     * branches below HQ (and HQ itself); a branch user returns just one.
     *
     * @return array<int, int>
     */
    public function accessibleBranchIds(Model $user): array;
}
