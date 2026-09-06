<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dental_examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('periodic'); // e.g., initial, periodic, emergency
            $table->timestamp('examined_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->string('status')->default('completed');
            $table->timestamps();
        });

        Schema::create('tooth_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dental_examination_id')->constrained()->cascadeOnDelete();
            $table->string('tooth_number_fdi');
            $table->string('finding_type'); // e.g., caries, missing, implant, crown, healthy
            $table->string('status')->default('existing'); // existing, observed
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['dental_examination_id', 'tooth_number_fdi']);
        });

        Schema::create('tooth_surface_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tooth_finding_id')->constrained()->cascadeOnDelete();
            $table->enum('surface', ['mesial', 'distal', 'occlusal', 'lingual', 'buccal']);
            $table->string('finding_type'); // e.g., caries, composite_filled
            $table->string('status')->default('existing');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['tooth_finding_id', 'surface']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tooth_surface_findings');
        Schema::dropIfExists('tooth_findings');
        Schema::dropIfExists('dental_examinations');
    }
};
