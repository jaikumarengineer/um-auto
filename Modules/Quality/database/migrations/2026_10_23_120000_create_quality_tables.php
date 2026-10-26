<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approved_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('gstin')->nullable();
            $table->string('status')->default('pending');
            $table->date('approved_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('incoming_parts', function (Blueprint $table) {
            $table->id();
            $table->string('part_number')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('uom', 20)->default('PCS');
            $table->string('category')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('approved_vendors')->nullOnDelete();
            $table->string('drawing_revision')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sampling_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('standard_ref')->default('ISO 2859-1');
            $table->string('inspection_level')->default('II');
            $table->decimal('aql', 6, 3)->default(1);
            $table->unsignedInteger('lot_min');
            $table->unsignedInteger('lot_max');
            $table->unsignedInteger('sample_size');
            $table->unsignedInteger('accept_number')->default(0);
            $table->unsignedInteger('reject_number')->default(1);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inspection_standards', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('part_id')->constrained('incoming_parts')->restrictOnDelete();
            $table->foreignId('sampling_plan_id')->nullable()->constrained('sampling_plans')->nullOnDelete();
            $table->string('revision')->default('A');
            $table->string('title');
            $table->date('effective_from');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inspection_characteristics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_standard_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('name');
            $table->string('specification');
            $table->string('method')->nullable();
            $table->string('instrument')->nullable();
            $table->decimal('tolerance_min', 14, 4)->nullable();
            $table->decimal('tolerance_max', 14, 4)->nullable();
            $table->string('unit')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('vendor_id')->constrained('approved_vendors')->restrictOnDelete();
            $table->date('po_date');
            $table->date('expected_date')->nullable();
            $table->string('status')->default('open');
            $table->string('currency', 8)->default('INR');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('incoming_parts')->restrictOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->decimal('ordered_qty', 14, 3);
            $table->string('uom', 20)->default('PCS');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_no']);
        });

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('approved_vendors')->restrictOnDelete();
            $table->date('invoice_date');
            $table->string('status')->default('verified');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('part_id')->constrained('incoming_parts')->restrictOnDelete();
            $table->decimal('invoiced_qty', 14, 3);
            $table->timestamps();
        });

        Schema::create('incoming_lots', function (Blueprint $table) {
            $table->id();
            $table->string('lot_number')->unique();
            $table->foreignId('purchase_invoice_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('incoming_parts')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('approved_vendors')->restrictOnDelete();
            $table->decimal('qty', 14, 3);
            $table->dateTime('received_at');
            $table->string('status')->default('received');
            $table->timestamps();
        });

        Schema::create('inspection_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number')->unique();
            $table->foreignId('incoming_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_standard_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sampling_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sample_size')->default(0);
            $table->decimal('inspected_qty', 14, 3)->default(0);
            $table->decimal('accepted_qty', 14, 3)->default(0);
            $table->decimal('rejected_qty', 14, 3)->default(0);
            $table->string('result')->default('pass');
            $table->dateTime('inspected_at');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_characteristic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('characteristic_name');
            $table->string('specification');
            $table->string('measured_value')->nullable();
            $table->string('result')->default('ok');
            $table->boolean('is_critical')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('quality_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->string('alert_type');
            $table->string('recipient');
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('logged');
            $table->date('alert_on');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['alert_on', 'alert_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_alerts');
        Schema::dropIfExists('inspection_results');
        Schema::dropIfExists('inspection_reports');
        Schema::dropIfExists('incoming_lots');
        Schema::dropIfExists('purchase_invoice_lines');
        Schema::dropIfExists('purchase_invoices');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('inspection_characteristics');
        Schema::dropIfExists('inspection_standards');
        Schema::dropIfExists('sampling_plans');
        Schema::dropIfExists('incoming_parts');
        Schema::dropIfExists('approved_vendors');
    }
};
