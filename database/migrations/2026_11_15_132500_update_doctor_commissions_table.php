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
            if (!Schema::hasColumn('doctor_commissions', 'practice_id')) {
                $table->foreignId('practice_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
        });

        // In SQLite, altering column to nullable is safely handled
        try {
            // Populate practice_id from existing payments
            \Illuminate\Support\Facades\DB::statement('
                UPDATE doctor_commissions 
                SET practice_id = (SELECT practice_id FROM payments WHERE payments.id = doctor_commissions.payment_id)
                WHERE practice_id IS NULL AND payment_id IS NOT NULL
            ');
        } catch (\Throwable $e) {
            // fallback
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_commissions', function (Blueprint $table) {
            $table->dropColumn(['practice_id']);
        });
    }
};
