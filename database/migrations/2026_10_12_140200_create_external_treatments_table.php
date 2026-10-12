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
        Schema::create('external_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('procedure_title'); // e.g. Root Canal Treatment, Dental Implant, Crown/Bridge, Ortho
            $table->unsignedInteger('tooth_number_fdi')->nullable(); // FDI tooth number if tooth-specific
            $table->string('performed_year')->nullable(); // e.g. "2021", "approx 5 years ago"
            $table->string('clinic_or_doctor_name')->nullable(); // External clinic or specialist name
            $table->text('notes')->nullable();
            $table->json('attachments_json')->nullable(); // Uploaded external X-rays / documents
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_treatments');
    }
};
