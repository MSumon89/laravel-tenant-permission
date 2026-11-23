<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade path for databases created by an older copy of the package that
 * had no is_admin column. Fresh installs already have it, so this is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        if (Schema::hasColumn($table, 'is_admin')) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false);
        });
    }

    public function down(): void
    {
        // Intentionally empty: the column may belong to the create migration.
    }
};
