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
        Schema::create('voice_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained('practices')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('tooth_number_fdi')->nullable();
            $table->foreignId('treatment_procedure_id')->nullable()->constrained('treatment_procedures')->nullOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('disk')->default('local');
            $table->string('mime_type')->default('audio/webm');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->string('share_token', 64)->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->string('whatsapp_status')->default('link_prepared');
            $table->timestamps();

            $table->index(['practice_id', 'patient_id']);
            $table->index('share_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_notes');
    }
};
