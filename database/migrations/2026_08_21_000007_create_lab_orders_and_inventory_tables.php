<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dental_labs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dental_lab_id')->constrained()->cascadeOnDelete();
            $table->integer('tooth_number_fdi')->nullable();
            $table->string('shade')->nullable(); // VITA Shade guide e.g. A1, A2, B1
            $table->string('material')->nullable(); // Zirconia, E-Max, PFM, Acrylic
            $table->text('instructions')->nullable();
            $table->decimal('cost', 10, 2)->default(0.00);
            $table->enum('status', ['impression_sent', 'in_production', 'delivered', 'fitted', 'returned_for_redo'])->default('impression_sent');
            $table->dateTime('sent_at');
            $table->dateTime('expected_delivery_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->string('category')->default('Consumables'); // Restorative, Endo, Surgical, Disinfectants, Personal Protective Equipment
            $table->string('unit')->default('pcs'); // pcs, boxes, ml, syringes
            $table->integer('min_reorder_level')->default(10);
            $table->integer('reorder_quantity')->default(50);
            $table->timestamps();
        });

        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->date('expiry_date')->nullable();
            $table->decimal('unit_cost', 10, 2)->default(0.00);
            $table->integer('quantity_received');
            $table->integer('quantity_remaining');
            $table->timestamps();
        });

        Schema::create('procedure_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_procedure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_consumed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_consumptions');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('dental_labs');
    }
};
