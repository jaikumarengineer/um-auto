<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_characteristics', function (Blueprint $table) {
            $table->string('characteristic_class', 4)->default('C')->after('inspection_stage');
        });

        // Preserve the old boolean: non-critical lines become Major until edited.
        DB::table('inspection_characteristics')
            ->where('is_critical', false)
            ->update(['characteristic_class' => 'M']);
    }

    public function down(): void
    {
        Schema::table('inspection_characteristics', function (Blueprint $table) {
            $table->dropColumn('characteristic_class');
        });
    }
};
