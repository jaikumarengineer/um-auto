<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_parts', function (Blueprint $table) {
            $table->date('approved_on')->nullable()->after('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_parts', function (Blueprint $table) {
            $table->dropColumn('approved_on');
        });
    }
};
