<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completes the 6-site periodontal chart: suppuration per site, mobility and furcation per
 * tooth, and an optional link to the dental examination it was recorded in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perio_chartings', function (Blueprint $table) {
            $table->foreignId('dental_examination_id')->nullable()->after('patient_id')->constrained()->nullOnDelete();
            $table->json('suppuration_json')->nullable()->after('bleeding_on_probing_json');
            $table->json('mobility_json')->nullable()->after('plaque_index_json');   // { "16": 1 } (0–3)
            $table->json('furcation_json')->nullable()->after('mobility_json');      // { "16": 2 } (0–4, worst site)
        });
    }

    public function down(): void
    {
        Schema::table('perio_chartings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dental_examination_id');
            $table->dropColumn(['suppuration_json', 'mobility_json', 'furcation_json']);
        });
    }
};
