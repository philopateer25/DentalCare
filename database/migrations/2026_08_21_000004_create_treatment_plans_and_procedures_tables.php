<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Diagnostic, Preventive, Restorative, Endodontics, Periodontics, Prosthodontics, Surgery, Ortho
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('procedure_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('procedure_categories')->cascadeOnDelete();
            $table->string('code')->unique(); // CDT code e.g. D2140, D3310
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('standard_fee', 10, 2)->default(0.00);
            $table->integer('estimated_duration_minutes')->default(30);
            $table->timestamps();
        });

        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->default('Comprehensive Treatment Plan');
            $table->enum('status', ['draft', 'approved', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('net_amount', 10, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('treatment_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_plan_id')->constrained()->cascadeOnDelete();
            $table->integer('sequence')->default(1); // 1: Urgent, 2: Restorative, 3: Prosthetics
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('treatment_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_phase_id')->constrained()->cascadeOnDelete();
            $table->integer('tooth_number_fdi')->nullable();
            $table->enum('surface', ['M', 'D', 'O', 'L', 'B', 'I', 'ROOT', 'WHOLE'])->default('WHOLE');
            $table->foreignId('procedure_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('fee', 10, 2);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('net_amount', 10, 2);
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_procedures');
        Schema::dropIfExists('treatment_phases');
        Schema::dropIfExists('treatment_plans');
        Schema::dropIfExists('procedure_codes');
        Schema::dropIfExists('procedure_categories');
    }
};
