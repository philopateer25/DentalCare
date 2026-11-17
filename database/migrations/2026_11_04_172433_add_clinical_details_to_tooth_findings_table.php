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
        Schema::table('tooth_findings', function (Blueprint $table) {
            if (!Schema::hasColumn('tooth_findings', 'clinical_details')) {
                $table->json('clinical_details')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tooth_findings', function (Blueprint $table) {
            if (Schema::hasColumn('tooth_findings', 'clinical_details')) {
                $table->dropColumn('clinical_details');
            }
        });
    }
};
