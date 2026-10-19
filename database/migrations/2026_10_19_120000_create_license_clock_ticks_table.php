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
        Schema::create('license_clock_ticks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tick_sequence')->index();
            $table->unsignedBigInteger('accumulated_seconds');
            $table->timestamp('os_clock_at_tick');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_clock_ticks');
    }
};
