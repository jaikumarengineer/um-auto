<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('inspection_report_samples');

        Schema::create('inspection_report_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_report_id')->constrained()->cascadeOnDelete();
            $table->string('characteristic_class', 8);
            $table->decimal('aql', 6, 3);
            $table->unsignedInteger('lot_min');
            $table->unsignedInteger('lot_max');
            $table->unsignedInteger('sample_size');
            $table->decimal('sampling_rate', 8, 6);
            $table->unsignedInteger('accept_number')->default(0);
            $table->unsignedInteger('reject_number')->default(1);
            $table->unsignedInteger('supplier_defectives')->default(0);
            $table->unsignedInteger('umac_defectives')->default(0);
            $table->decimal('supplier_nonconforming_pct', 6, 2)->default(0);
            $table->decimal('umac_nonconforming_pct', 6, 2)->default(0);
            $table->string('supplier_result', 16);
            $table->string('umac_result', 16);
            $table->foreignId('sampling_plan_id')->nullable()->constrained('sampling_plans')->nullOnDelete();
            $table->timestamps();

            $table->unique(['inspection_report_id', 'characteristic_class'], 'report_sample_class_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_report_samples');
    }
};
