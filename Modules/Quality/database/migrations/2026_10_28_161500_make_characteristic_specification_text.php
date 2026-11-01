<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_characteristics', function (Blueprint $table) {
            $table->text('specification')->change();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_characteristics', function (Blueprint $table) {
            $table->string('specification')->change();
        });
    }
};
