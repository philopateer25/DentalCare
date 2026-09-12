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
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('type')->default('whatsapp'); // Could be whatsapp, sms, email
            $table->text('message')->nullable();
            $table->string('status')->nullable(); // sent, delivered, read, received
            $table->string('message_id')->nullable(); // External ID from WhatsApp/Evolution API
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};
