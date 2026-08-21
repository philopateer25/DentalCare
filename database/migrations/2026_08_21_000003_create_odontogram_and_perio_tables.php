<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tooth_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->integer('tooth_number_fdi'); // 11-48 adult, 51-85 pediatric
            $table->integer('mobility_class')->default(0); // 0, 1, 2, 3
            $table->integer('furcation_grade')->default(0); // 0, 1, 2, 3, 4
            $table->boolean('is_missing')->default(false);
            $table->boolean('is_impacted')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'tooth_number_fdi']);
        });

        Schema::create('surface_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tooth_record_id')->constrained()->cascadeOnDelete();
            $table->enum('surface', ['M', 'D', 'O', 'L', 'B', 'I', 'ROOT', 'WHOLE'])->default('WHOLE'); // Mesial, Distal, Occlusal, Lingual, Buccal, Incisal, Root, Whole
            $table->string('condition_code'); // e.g. CARIES, COMPOSITE, AMALGAM, RCT, CROWN, IMPLANT, VENEER, EXTRACTION_PLANNED
            $table->enum('status', ['existing', 'active', 'planned', 'completed'])->default('active');
            $table->string('material')->nullable(); // Amalgam, Composite, Porcilain, Zirconia
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('perio_chartings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->date('exam_date');
            $table->foreignId('examined_by_doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('probe_depths_json'); // Array of 6 site depths per tooth: { "11": [2,2,3,2,2,2], ... }
            $table->json('bleeding_on_probing_json')->nullable(); // Array of booleans for 6 sites per tooth
            $table->json('gingival_margins_json')->nullable();
            $table->json('plaque_index_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perio_chartings');
        Schema::dropIfExists('surface_conditions');
        Schema::dropIfExists('tooth_records');
    }
};
