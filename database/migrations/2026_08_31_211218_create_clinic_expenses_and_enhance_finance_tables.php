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
        Schema::create('clinic_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->string('expense_number')->unique();
            $table->string('category');
            $table->string('payee');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dental_lab_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->string('payment_method')->default('bank_transfer');
            $table->string('reference_number')->nullable();
            $table->boolean('tax_deductible')->default(true);
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_frequency')->nullable(); // monthly, quarterly, yearly
            $table->text('receipt_url')->nullable();
            $table->foreignId('logged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->default(0.00)->after('invoice_number');
            $table->decimal('discount_amount', 10, 2)->default(0.00)->after('subtotal');
            $table->decimal('tax_amount', 10, 2)->default(0.00)->after('discount_amount');
            $table->decimal('insurance_covered_amount', 10, 2)->default(0.00)->after('tax_amount');
            $table->decimal('patient_copay_amount', 10, 2)->default(0.00)->after('insurance_covered_amount');
            $table->text('terms_and_conditions')->nullable()->after('due_date');
            $table->text('notes')->nullable()->after('terms_and_conditions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal',
                'discount_amount',
                'tax_amount',
                'insurance_covered_amount',
                'patient_copay_amount',
                'terms_and_conditions',
                'notes',
            ]);
        });

        Schema::dropIfExists('clinic_expenses');
    }
};
