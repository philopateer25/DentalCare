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
        Schema::table('invoice_items', function (Blueprint $table) {
            // Make existing field nullable to allow polymorphic relation to handle it or other types
            $table->foreignId('treatment_procedure_id')->nullable()->change();
            
            // Add polymorphic relation fields (e.g. for LabOrder, Appointment, etc)
            $table->nullableMorphs('invoiceable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropMorphs('invoiceable');
            // Reverting nullable is risky if data was added, but strictly speaking:
            $table->foreignId('treatment_procedure_id')->nullable(false)->change();
        });
    }
};
