<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Models\RecordActivity;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_record_activities', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'id']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_record_activities');
    }

    private function backfill(): void
    {
        $maps = [
            'purchase_orders' => PurchaseOrder::class,
            'purchase_invoices' => PurchaseInvoice::class,
            'inspection_reports' => InspectionReport::class,
        ];

        foreach ($maps as $table => $type) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'created_by')) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($type) {
                $inserts = [];

                foreach ($rows as $row) {
                    $createdAt = $row->created_at ?? now();

                    $inserts[] = [
                        'subject_type' => $type,
                        'subject_id' => $row->id,
                        'event' => RecordActivity::EVENT_CREATED,
                        'actor_id' => $row->created_by ?? null,
                        'from_status' => null,
                        'to_status' => null,
                        'reason' => null,
                        'meta' => json_encode(['backfill' => true]),
                        'created_at' => $createdAt,
                    ];

                    $status = $row->approval_status ?? null;

                    if ($status && $status !== 'pending' && ($row->approved_by || $row->approved_at)) {
                        $inserts[] = [
                            'subject_type' => $type,
                            'subject_id' => $row->id,
                            'event' => RecordActivity::EVENT_APPROVAL_STATUS_CHANGED,
                            'actor_id' => $row->approved_by ?? $row->created_by ?? null,
                            'from_status' => 'pending',
                            'to_status' => $status,
                            'reason' => $row->rejection_reason ?? null,
                            'meta' => json_encode(['backfill' => true]),
                            'created_at' => $row->approved_at ?? $createdAt,
                        ];
                    }
                }

                if ($inserts !== []) {
                    DB::table('quality_record_activities')->insert($inserts);
                }
            });
        }
    }
};
