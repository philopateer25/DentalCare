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
        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('role');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('national_id')->nullable();
            $table->string('employment_type')->default('Full-Time');
            $table->date('hire_date')->nullable();
            $table->decimal('base_salary', 10, 2)->default(0.00)->nullable();
            $table->decimal('hourly_rate', 10, 2)->default(0.00)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();
            $table->string('payslip_number')->unique();
            $table->integer('pay_period_month');
            $table->integer('pay_period_year');
            $table->decimal('base_salary', 10, 2)->default(0.00);
            $table->decimal('overtime_amount', 10, 2)->default(0.00)->nullable();
            $table->decimal('bonus_amount', 10, 2)->default(0.00)->nullable();
            $table->decimal('allowance_amount', 10, 2)->default(0.00)->nullable();
            $table->decimal('tax_deduction', 10, 2)->default(0.00)->nullable();
            $table->decimal('insurance_deduction', 10, 2)->default(0.00)->nullable();
            $table->decimal('other_deductions', 10, 2)->default(0.00)->nullable();
            $table->decimal('net_salary', 10, 2)->default(0.00);
            $table->string('payment_method')->default('bank_direct_deposit');
            $table->string('status')->default('draft'); // draft, approved, disbursed
            $table->dateTime('disbursed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('payer_id')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('claims_email')->nullable();
            $table->string('portal_url')->nullable();
            $table->text('claims_address')->nullable();
            $table->integer('standard_reimbursement_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->cascadeOnDelete();
            $table->string('policy_number');
            $table->string('group_number')->nullable();
            $table->string('subscriber_name');
            $table->string('subscriber_relationship')->default('self');
            $table->string('plan_type')->default('PPO');
            $table->decimal('annual_maximum', 10, 2)->default(1500.00);
            $table->decimal('annual_deductible', 10, 2)->default(50.00);
            $table->decimal('deductible_met', 10, 2)->default(0.00);
            $table->decimal('preventive_coverage_pct', 5, 2)->default(100.00);
            $table->decimal('basic_coverage_pct', 5, 2)->default(80.00);
            $table->decimal('major_coverage_pct', 5, 2)->default(50.00);
            $table->decimal('ortho_coverage_pct', 5, 2)->default(50.00);
            $table->decimal('ortho_lifetime_max', 10, 2)->default(1500.00);
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->string('claim_number')->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_insurance_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->string('claim_type')->default('standard_claim'); // standard_claim, pre_authorization
            $table->decimal('total_claimed_amount', 10, 2);
            $table->decimal('estimated_insurance_amount', 10, 2)->default(0.00)->nullable();
            $table->decimal('patient_copay_amount', 10, 2)->default(0.00)->nullable();
            $table->decimal('actual_paid_amount', 10, 2)->default(0.00)->nullable();
            $table->string('eob_reference_number')->nullable();
            $table->string('status')->default('draft'); // draft, submitted_edi, under_review, approved_paid, partially_approved, denied, appealed
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('adjudicated_at')->nullable();
            $table->text('denial_reason')->nullable();
            $table->text('treatment_summary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('patient_insurance_policies');
        Schema::dropIfExists('insurance_providers');
        Schema::dropIfExists('payroll_slips');
        Schema::dropIfExists('staff_members');
    }
};
