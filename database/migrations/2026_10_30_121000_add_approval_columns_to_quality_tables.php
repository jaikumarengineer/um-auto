<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_orders', 'purchase_invoices', 'inspection_reports'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'created_by')) {
                    $blueprint->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn($table, 'approval_status')) {
                    $blueprint->string('approval_status', 20)->default('approved')->after('created_by');
                }

                if (! Schema::hasColumn($table, 'approved_by')) {
                    $blueprint->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn($table, 'approved_at')) {
                    $blueprint->timestamp('approved_at')->nullable();
                }

                if (! Schema::hasColumn($table, 'rejection_reason')) {
                    $blueprint->text('rejection_reason')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['purchase_orders', 'purchase_invoices', 'inspection_reports'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach (['rejection_reason', 'approved_at', 'approved_by', 'approval_status', 'created_by'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        if (in_array($column, ['created_by', 'approved_by'], true)) {
                            $blueprint->dropConstrainedForeignId($column);
                        } else {
                            $blueprint->dropColumn($column);
                        }
                    }
                }
            });
        }
    }
};
