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
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->string('file_number')->index();
            $table->string('national_id')->nullable()->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('gender', ['male', 'female', 'other'])->default('male');
            $table->date('dob')->nullable();
            $table->string('phone');
            $table->string('whatsapp_number')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->timestamps();
        });

        Schema::create('medical_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('blood_type')->nullable(); // A+, A-, B+, B-, AB+, AB-, O+, O-
            $table->boolean('diabetic_status')->default(false);
            $table->boolean('cardiac_history')->default(false);
            $table->boolean('hypertension_status')->default(false);
            $table->boolean('bleeding_disorder')->default(false);
            $table->boolean('latex_allergy')->default(false);
            $table->boolean('penicillin_allergy')->default(false);
            $table->boolean('local_anesthetic_allergy')->default(false);
            $table->json('medical_conditions_json')->nullable(); // Additional conditions tags
            $table->json('active_medications_json')->nullable(); // List of current meds
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
