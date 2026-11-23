<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade path for databases created by an older copy of the package that
 * had no roles.description column. Fresh installs already have it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        if (Schema::hasColumn($table, 'description')) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->string('description')->nullable();
        });
    }

    public function down(): void
    {
        // Intentionally empty: the column may belong to the create migration.
    }
};
