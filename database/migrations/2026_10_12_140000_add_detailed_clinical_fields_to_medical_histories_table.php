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
        Schema::table('medical_histories', function (Blueprint $table) {
            // Diabetes Mellitus Details
            $table->string('diabetes_type')->nullable()->after('diabetic_status'); // type_1, type_2, gestational, pre_diabetic
            $table->string('hba1c_level')->nullable()->after('diabetes_type'); // <6.5%, 6.5-7.5%, 7.6-8.5%, >8.5%
            $table->unsignedInteger('blood_sugar_reading')->nullable()->after('hba1c_level'); // mg/dL
            $table->json('diabetes_medications')->nullable()->after('blood_sugar_reading');
            $table->boolean('hypoglycemia_history')->default(false)->after('diabetes_medications');

            // Hypertension, Vitals & Cardiovascular Details
            $table->string('blood_pressure_stage')->nullable()->after('hypertension_status'); // normal, elevated, stage_1, stage_2, crisis
            $table->string('typical_blood_pressure')->nullable()->after('blood_pressure_stage'); // e.g. "120/80"
            $table->json('cardiac_conditions_json')->nullable()->after('typical_blood_pressure'); // pacemaker, artificial_valve, previous_mi, etc.
            $table->boolean('requires_antibiotic_prophylaxis')->default(false)->after('cardiac_conditions_json');

            // Bleeding Disorders & Anticoagulant Therapy
            $table->string('anticoagulant_type')->nullable()->after('bleeding_disorder'); // aspirin, plavix, warfarin, eliquis, xarelto, none, other
            $table->decimal('inr_level', 4, 2)->nullable()->after('anticoagulant_type');

            // Bone Antiresorptive Therapy (Critical for MRONJ risk in oral surgery / implants)
            $table->boolean('bisphosphonate_therapy')->default(false)->after('inr_level');
            $table->string('bisphosphonate_details')->nullable()->after('bisphosphonate_therapy'); // Drug name, route (oral/IV), duration

            // Dental-Specific Flags & Special Conditions
            $table->string('dental_anxiety_level')->default('low')->after('bisphosphonate_details'); // low, moderate, severe_phobia
            $table->boolean('severe_gag_reflex')->default(false)->after('dental_anxiety_level');
            $table->string('pregnancy_trimester')->nullable()->after('severe_gag_reflex'); // none, first, second, third, nursing
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_histories', function (Blueprint $table) {
            $table->dropColumn([
                'diabetes_type',
                'hba1c_level',
                'blood_sugar_reading',
                'diabetes_medications',
                'hypoglycemia_history',
                'blood_pressure_stage',
                'typical_blood_pressure',
                'cardiac_conditions_json',
                'requires_antibiotic_prophylaxis',
                'anticoagulant_type',
                'inr_level',
                'bisphosphonate_therapy',
                'bisphosphonate_details',
                'dental_anxiety_level',
                'severe_gag_reflex',
                'pregnancy_trimester',
            ]);
        });
    }
};
