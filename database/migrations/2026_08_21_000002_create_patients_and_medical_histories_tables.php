<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_number')->index();
            $table->string('full_name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('national_id', 14)->nullable()->index();
            $table->string('phone');
            $table->string('secondary_phone')->nullable();
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->default('male');
            $table->string('blood_type')->nullable(); // A+, A-, B+, B-, AB+, AB-, O+, O-
            $table->string('referral_source')->nullable(); // Social Media, Walk-in, Patient Referral, Doctor Referral
            $table->string('whatsapp_number')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            // Medical alerts & flags
            $table->boolean('penicillin_allergy')->default(false);
            $table->boolean('latex_allergy')->default(false);
            $table->boolean('bleeding_disorder')->default(false);
            $table->boolean('cardiac_condition')->default(false);
            $table->boolean('hypertension')->default(false);
            $table->boolean('diabetic')->default(false);
            $table->boolean('hepatitis')->default(false);
            $table->boolean('pregnant')->default(false);
            $table->json('medical_alerts_json')->nullable();
            $table->text('medical_notes')->nullable();

            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->timestamps();
        });

        Schema::create('medical_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('blood_type')->nullable();
            $table->boolean('diabetic_status')->default(false);
            $table->boolean('cardiac_history')->default(false);
            $table->boolean('hypertension_status')->default(false);
            $table->boolean('bleeding_disorder')->default(false);
            $table->boolean('latex_allergy')->default(false);
            $table->boolean('penicillin_allergy')->default(false);
            $table->boolean('local_anesthetic_allergy')->default(false);
            $table->json('medical_conditions_json')->nullable();
            $table->json('active_medications_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_histories');
        Schema::dropIfExists('patients');
    }
};
