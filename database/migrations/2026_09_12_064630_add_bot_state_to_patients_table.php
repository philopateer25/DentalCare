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
        Schema::table('patients', function (Blueprint $table) {
            $table->string('bot_state')->nullable()->default('menu'); // e.g. menu, registering, booking, human
            $table->string('bot_step')->nullable(); // e.g. ask_name, ask_age
            $table->json('bot_data')->nullable(); // Temporary storage for registration or booking info
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['bot_state', 'bot_step', 'bot_data']);
        });
    }
};
