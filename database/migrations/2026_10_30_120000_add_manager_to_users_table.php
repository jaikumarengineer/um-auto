<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh installs (including SQLite tests) already include manager on create_users.
        // Existing MySQL databases still need the enum widened.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `users` MODIFY `user_type` ENUM('admin', 'sub_admin', 'staff', 'user', 'purchase', 'incoming', 'manager') NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE `users` SET `user_type` = 'staff' WHERE `user_type` = 'manager'");
        DB::statement("ALTER TABLE `users` MODIFY `user_type` ENUM('admin', 'sub_admin', 'staff', 'user', 'purchase', 'incoming') NOT NULL DEFAULT 'user'");
    }
};
