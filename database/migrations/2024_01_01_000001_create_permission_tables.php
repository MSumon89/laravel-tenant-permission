<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the five permission tables. Every table is skipped if it already
 * exists, so running this on a project that already has them is a no-op.
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

        // A single global catalog of permission names, e.g. "posts.edit".
        if (! Schema::hasTable($tableNames['permissions'])) {
            Schema::create($tableNames['permissions'], function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->string('display_name')->nullable();
                $table->string('module')->nullable();
                $table->string('description')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->unique(['name', 'guard_name']);
            });
        }

        // Roles are scoped to a tenant. tenant_id = 0 is the global scope.
        if (! Schema::hasTable($tableNames['roles'])) {
            Schema::create($tableNames['roles'], function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(0);
                $table->string('name');
                $table->string('guard_name');
                $table->string('description')->nullable();
                $table->boolean('is_system')->default(false); // cannot be deleted
                $table->boolean('is_admin')->default(false);  // holds every permission in its tenant
                $table->timestamps();

                $table->unique(['tenant_id', 'name', 'guard_name']);
                $table->index('tenant_id');
            });
        }

        if (! Schema::hasTable($tableNames['role_has_permissions'])) {
            Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames): void {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');

                $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->cascadeOnDelete();
                $table->foreign('role_id')->references('id')->on($tableNames['roles'])->cascadeOnDelete();

                $table->primary(['permission_id', 'role_id']);
            });
        }

        // Which roles a user holds. tenant_id is copied from the role so
        // "this user's roles in tenant X" is a single indexed lookup.
        if (! Schema::hasTable($tableNames['model_has_roles'])) {
            Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $morphKey): void {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger($morphKey);
                $table->unsignedBigInteger('tenant_id')->default(0);

                $table->foreign('role_id')->references('id')->on($tableNames['roles'])->cascadeOnDelete();

                $table->index([$morphKey, 'model_type'], 'model_has_roles_model_index');
                $table->index('tenant_id');
                $table->primary(['role_id', $morphKey, 'model_type', 'tenant_id'], 'model_has_roles_primary');
            });
        }

        // Per-user overrides: 'grant' adds a permission, 'deny' removes one.
        // Deny always wins.
        if (! Schema::hasTable($tableNames['model_has_permissions'])) {
            Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $morphKey): void {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger($morphKey);
                $table->unsignedBigInteger('tenant_id')->default(0);
                $table->enum('type', ['grant', 'deny'])->default('grant');
                $table->timestamps();

                $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->cascadeOnDelete();

                $table->index([$morphKey, 'model_type'], 'model_has_permissions_model_index');
                $table->unique(['permission_id', $morphKey, 'model_type', 'tenant_id'], 'model_has_permissions_unique');
            });
        }
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
