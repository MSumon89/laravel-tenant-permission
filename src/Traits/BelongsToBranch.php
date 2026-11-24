<?php

namespace BuildWithSmk\TenantPermission\Traits;

use BuildWithSmk\TenantPermission\Branches\BranchScope;
use BuildWithSmk\TenantPermission\PermissionRegistrar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Add to models whose rows belong to a branch (patients, invoices, ...).
 * Queries are filtered to the current branch (see BranchScope), and the
 * branch column is filled in on create when one branch is selected.
 *
 * The column is config('permission.branches.column'), or set
 * `protected string $branchColumn = 'office_id';` on the model.
 *
 * @mixin Model
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function (Model $model): void {
            $registrar = app(PermissionRegistrar::class);
            $column = $model->getBranchColumn();
            $current = $registrar->getCurrentBranchId();

            // In "All branches" mode there is no single branch to use: the
            // form must send one (validate it against the accessible ids).
            if ($registrar->branchesEnabled() && empty($model->getAttribute($column)) && $current) {
                $model->setAttribute($column, $current);
            }
        });
    }

    public function getBranchColumn(): string
    {
        return property_exists($this, 'branchColumn')
            ? $this->branchColumn
            : (string) config('permission.branches.column', 'branch_id');
    }

    /**
     * Escape hatch: query across every branch (reports for platform staff,
     * console jobs, ...).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutBranchScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(BranchScope::class);
    }

    /**
     * Narrow to one branch, e.g. a per-branch column in an "All branches"
     * report. The branch scope still applies, so a branch outside the
     * user's visible branches returns nothing.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where($this->qualifyColumn($this->getBranchColumn()), $branchId);
    }
}
