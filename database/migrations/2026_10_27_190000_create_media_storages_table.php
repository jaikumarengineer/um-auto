<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_storages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('extension', 32)->nullable();
            $table->string('path');
            $table->string('url');
            $table->string('disk')->default('public_uploads');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_storages');
    }
};
