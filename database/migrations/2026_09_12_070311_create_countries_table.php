<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();

            $table->string('iso', 2)->unique();
            $table->string('name');
            $table->string('nicename');
            $table->string('iso3', 3)->nullable()->unique();

            $table->unsignedSmallInteger('numcode')->nullable();
            $table->string('phonecode', 10)->nullable();

            $table->unsignedBigInteger('region_id')->nullable();

            $table->string('timezone')->nullable();
            $table->string('utcname')->nullable();
            $table->string('utc')->nullable();

            $table->text('abbreviations')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};