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
        Schema::table('doctor_commissions', function (Blueprint $table) {
            $table->foreignId('payroll_slip_id')->nullable()->after('practice_id')->constrained('payroll_slips')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_commissions', function (Blueprint $table) {
            $table->dropForeign(['payroll_slip_id']);
            $table->dropColumn('payroll_slip_id');
        });
    }
};
