<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_part_vendor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_part_id')->constrained('incoming_parts')->cascadeOnDelete();
            $table->foreignId('approved_vendor_id')->constrained('approved_vendors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['incoming_part_id', 'approved_vendor_id'], 'incoming_part_vendor_unique');
        });

        // Carry over existing single-vendor links.
        $rows = DB::table('incoming_parts')
            ->whereNotNull('vendor_id')
            ->get(['id', 'vendor_id']);

        $now = now();

        foreach ($rows as $row) {
            DB::table('incoming_part_vendor')->insertOrIgnore([
                'incoming_part_id' => $row->id,
                'approved_vendor_id' => $row->vendor_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_part_vendor');
    }
};
