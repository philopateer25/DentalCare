<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->default('other');
            $table->string('equipment_name')->nullable();
            $table->text('description')->nullable();
            // closes_branch | closes_rooms | no_closure
            $table->string('impact')->default('no_closure');
            // Shown to staff in the booking pop-up when the room stays operational
            $table->text('unavailable_items')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // scheduled | in_progress | completed | cancelled
            $table->string('status')->default('scheduled');
            $table->dateTime('completed_at')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('vendor_phone')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['practice_id', 'starts_at', 'ends_at']);
        });

        Schema::create('maintenance_record_operatory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operatory_id')->constrained()->cascadeOnDelete();
            $table->unique(['maintenance_record_id', 'operatory_id'], 'maint_rec_operatory_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_record_operatory');
        Schema::dropIfExists('maintenance_records');
    }
};
