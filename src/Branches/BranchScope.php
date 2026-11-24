<?php

namespace BuildWithSmk\TenantPermission\Branches;

use BuildWithSmk\TenantPermission\PermissionRegistrar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * WHERE <branch column> IN (<visible branches>).
 *
 * - One branch selected: that branch only.
 * - "All branches": the user's accessible branches, never everything.
 * - No branch context: no rows in an HTTP request (branches.strict), no
 *   filter in console commands and queue jobs.
 * - Branches disabled: no filter.
 *
 * It only reads the context pinned on the registrar, never auth(), so it
 * is safe on the User model itself.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->branchesEnabled()) {
            return;
        }

        $branchIds = $registrar->visibleBranchIds();

        if ($branchIds === null) {
            if ($registrar->branchScopeFailsClosed()) {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        if ($branchIds === []) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->whereIn($model->qualifyColumn($model->getBranchColumn()), $branchIds);
    }
}
