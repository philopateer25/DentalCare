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
        Schema::table('patient_teeth', function (Blueprint $table) {
            $table->string('status')->default('existing')->after('condition'); // existing, needs_treatment, watch
            $table->json('clinical_details')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_teeth', function (Blueprint $table) {
            $table->dropColumn(['status', 'clinical_details']);
        });
    }
};
