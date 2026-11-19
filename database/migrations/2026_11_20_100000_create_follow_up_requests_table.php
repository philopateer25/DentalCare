<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doctor → reception follow-up requests: the doctor asks for a follow-up from the chair,
 * reception sees it live on the dashboard and books the appointment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();       // who asked
            $table->foreignId('source_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('timeframe', 20);                                                // 1_week … 6_months, asap
            $table->date('preferred_date')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('procedure_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('urgent')->default(false);
            $table->string('status', 20)->default('pending');                              // pending, booked, cancelled
            $table->foreignId('booked_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('handled_note')->nullable();
            $table->timestamps();

            $table->index(['practice_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_requests');
    }
};
