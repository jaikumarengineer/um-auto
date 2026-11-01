<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_standards', function (Blueprint $table) {
            $table->string('supplier_name')->nullable()->after('notes');
            $table->string('supplier_code')->nullable()->after('supplier_name');
            $table->string('doc_no')->nullable()->after('supplier_code');
            $table->string('page_label')->nullable()->after('doc_no');
            $table->string('drawing_no')->nullable()->after('page_label');
            $table->string('drawing_rev')->nullable()->after('drawing_no');
            $table->string('approved_by')->nullable()->after('drawing_rev');
            $table->string('checked_by')->nullable()->after('approved_by');
            $table->string('prepared_by')->nullable()->after('checked_by');
            $table->string('document_path')->nullable()->after('prepared_by');
            $table->string('document_name')->nullable()->after('document_path');
            $table->unique('part_id');
        });

        Schema::table('inspection_characteristics', function (Blueprint $table) {
            $table->string('sample_plan')->nullable()->after('instrument');
            $table->string('inspection_stage')->nullable()->after('sample_plan');
            $table->text('remarks')->nullable()->after('inspection_stage');
        });

        Schema::create('inspection_standard_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_standard_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('rev_no');
            $table->date('rev_date');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_standard_revisions');

        Schema::table('inspection_characteristics', function (Blueprint $table) {
            $table->dropColumn(['sample_plan', 'inspection_stage', 'remarks']);
        });

        Schema::table('inspection_standards', function (Blueprint $table) {
            $table->dropUnique(['part_id']);
            $table->dropColumn([
                'supplier_name',
                'supplier_code',
                'doc_no',
                'page_label',
                'drawing_no',
                'drawing_rev',
                'approved_by',
                'checked_by',
                'prepared_by',
                'document_path',
                'document_name',
            ]);
        });
    }
};
