<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_reports', function (Blueprint $table) {
            $table->string('assembly_name')->nullable()->after('remarks');
            $table->string('process')->nullable()->after('assembly_name');
            $table->string('bill_no')->nullable()->after('process');
            $table->string('supplier_status', 16)->nullable()->after('bill_no');
            $table->string('umac_status', 16)->nullable()->after('supplier_status');
            $table->string('supplier_decision', 32)->default('conforming')->after('umac_status');
            $table->string('umac_decision', 32)->default('conforming')->after('supplier_decision');
            $table->foreignId('inspected_by')->nullable()->after('umac_decision')->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->after('inspected_by')->constrained('users')->nullOnDelete();
            $table->json('images')->nullable()->after('verified_by');
        });

        Schema::table('inspection_results', function (Blueprint $table) {
            $table->text('specification')->nullable()->change();
        });

        Schema::table('inspection_results', function (Blueprint $table) {
            $table->string('method')->nullable()->after('specification');
            $table->string('characteristic_class', 4)->nullable()->after('method');
            $table->json('supplier_readings')->nullable()->after('measured_value');
            $table->json('umac_readings')->nullable()->after('supplier_readings');
            $table->string('supplier_result', 16)->default('ok')->after('umac_readings');
            $table->string('umac_result', 16)->default('ok')->after('supplier_result');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_results', function (Blueprint $table) {
            $table->dropColumn([
                'method',
                'characteristic_class',
                'supplier_readings',
                'umac_readings',
                'supplier_result',
                'umac_result',
            ]);
        });

        Schema::table('inspection_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('inspected_by');
            $table->dropColumn([
                'assembly_name',
                'process',
                'bill_no',
                'supplier_status',
                'umac_status',
                'supplier_decision',
                'umac_decision',
                'images',
            ]);
        });
    }
};
