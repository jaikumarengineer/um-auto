<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $roles = $tableNames['roles'] ?? 'roles';

        if (! Schema::hasTable($roles)) {
            return;
        }

        Schema::table($roles, function (Blueprint $table) use ($roles) {
            if (! Schema::hasColumn($roles, 'description')) {
                $table->text('description')->nullable()->after('guard_name');
            }

            if (! Schema::hasColumn($roles, 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $roles = $tableNames['roles'] ?? 'roles';

        if (! Schema::hasTable($roles)) {
            return;
        }

        Schema::table($roles, function (Blueprint $table) use ($roles) {
            if (Schema::hasColumn($roles, 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn($roles, 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
