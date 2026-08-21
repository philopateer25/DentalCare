<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0.00);
            $table->decimal('balance_due', 10, 2);
            $table->enum('status', ['unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'])->default('unpaid');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
        });

        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_funded_amount', 10, 2);
            $table->decimal('down_payment', 10, 2)->default(0.00);
            $table->integer('number_of_installments');
            $table->enum('frequency', ['weekly', 'bi_weekly', 'monthly', 'milestone'])->default('monthly');
            $table->enum('status', ['active', 'completed', 'defaulted'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('installment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_plan_id')->constrained()->cascadeOnDelete();
            $table->integer('schedule_number');
            $table->date('due_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0.00);
            $table->enum('status', ['pending', 'partially_paid', 'paid', 'overdue'])->default('pending');
            $table->date('payment_date')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['cash', 'instapay', 'card_pos', 'insurance_tpa', 'bank_transfer'])->default('cash');
            $table->string('transaction_reference')->nullable(); // InstaPay ref / POS auth code / Check no.
            $table->foreignId('logged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('doctor_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('lab_deduction_amount', 10, 2)->default(0.00);
            $table->decimal('commission_percentage', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->enum('status', ['pending', 'accrued', 'settled'])->default('pending');
            $table->dateTime('settled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_commissions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('installment_schedules');
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
