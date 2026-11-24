<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds branch_id to role assignments and per-user overrides (v1.1).
 * branch_id = 0 means "every branch", so existing rows keep working as
 * before. The column joins the primary key / unique index, so one user can
 * hold the same role in two different branches.
 *
 * Skipped if the column already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');

        if (empty($tableNames)) {
            throw new RuntimeException('config/permission.php is not loaded. Run [php artisan config:clear] and try again.');
        }

        $roles = $tableNames['model_has_roles'];

        if (! Schema::hasColumn($roles, 'branch_id')) {
            Schema::table($roles, function (Blueprint $table): void {
                $table->unsignedBigInteger('branch_id')->default(0);
            });

            Schema::table($roles, function (Blueprint $table) use ($morphKey): void {
                // The role_id foreign key needs its own index once it is no
                // longer the first column of the primary key (MySQL).
                $table->index('role_id', 'model_has_roles_role_id_index');
                $table->dropPrimary('model_has_roles_primary');
                $table->primary(['role_id', $morphKey, 'model_type', 'tenant_id', 'branch_id'], 'model_has_roles_primary');
                $table->index('branch_id', 'model_has_roles_branch_id_index');
            });
        }

        $permissions = $tableNames['model_has_permissions'];

        if (! Schema::hasColumn($permissions, 'branch_id')) {
            Schema::table($permissions, function (Blueprint $table): void {
                $table->unsignedBigInteger('branch_id')->default(0);
            });

            Schema::table($permissions, function (Blueprint $table) use ($morphKey): void {
                $table->index('permission_id', 'model_has_permissions_permission_id_index');
                $table->dropUnique('model_has_permissions_unique');
                $table->unique(['permission_id', $morphKey, 'model_type', 'tenant_id', 'branch_id'], 'model_has_permissions_unique');
            });
        }
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');

        $roles = $tableNames['model_has_roles'];

        if (Schema::hasColumn($roles, 'branch_id')) {
            Schema::table($roles, function (Blueprint $table) use ($morphKey): void {
                $table->dropIndex('model_has_roles_branch_id_index');
                $table->dropPrimary('model_has_roles_primary');
                $table->primary(['role_id', $morphKey, 'model_type', 'tenant_id'], 'model_has_roles_primary');
                $table->dropIndex('model_has_roles_role_id_index');
            });

            Schema::table($roles, fn (Blueprint $table) => $table->dropColumn('branch_id'));
        }

        $permissions = $tableNames['model_has_permissions'];

        if (Schema::hasColumn($permissions, 'branch_id')) {
            Schema::table($permissions, function (Blueprint $table) use ($morphKey): void {
                $table->dropUnique('model_has_permissions_unique');
                $table->unique(['permission_id', $morphKey, 'model_type', 'tenant_id'], 'model_has_permissions_unique');
                $table->dropIndex('model_has_permissions_permission_id_index');
            });

            Schema::table($permissions, fn (Blueprint $table) => $table->dropColumn('branch_id'));
        }
    }
};
