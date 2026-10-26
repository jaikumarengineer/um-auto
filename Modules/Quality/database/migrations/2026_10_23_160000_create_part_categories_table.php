<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('incoming_parts', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('uom')->constrained('part_categories')->nullOnDelete();
        });

        $names = DB::table('incoming_parts')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        foreach ($names as $name) {
            $id = DB::table('part_categories')->insertGetId([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('incoming_parts')->where('category', $name)->update(['category_id' => $id]);
        }

        Schema::table('incoming_parts', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_parts', function (Blueprint $table) {
            $table->string('category')->nullable()->after('uom');
        });

        $categories = DB::table('part_categories')->pluck('name', 'id');

        foreach ($categories as $id => $name) {
            DB::table('incoming_parts')->where('category_id', $id)->update(['category' => $name]);
        }

        Schema::table('incoming_parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('part_categories');
    }
};
