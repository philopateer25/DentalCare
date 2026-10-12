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
        Schema::table('treatment_procedures', function (Blueprint $table) {
            // Intraoperative Anesthesia & Dosage
            $table->string('anesthetic_agent')->nullable()->after('status'); // e.g. Articaine 4% 1:100k, Lidocaine 2% 1:100k
            $table->string('anesthetic_dosage')->nullable()->after('anesthetic_agent'); // e.g. 1 carpule (1.8ml), 2 carpules (3.6ml)
            $table->string('anesthetic_technique')->nullable()->after('anesthetic_dosage'); // Infiltration, IAN Block, Mental Block, etc.

            // Tools & Clinical Materials
            $table->json('tools_used_json')->nullable()->after('anesthetic_technique'); // Rubber Dam, Apex Locator, Rotary Endo, etc.
            $table->json('materials_used_json')->nullable()->after('tools_used_json'); // Composite Shade A2, Bioceramic Sealer, Suture 3-0, etc.

            // Clinical Execution Outcome
            $table->boolean('hemostasis_achieved')->default(true)->after('materials_used_json');
            $table->text('chairside_notes')->nullable()->after('hemostasis_achieved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treatment_procedures', function (Blueprint $table) {
            $table->dropColumn([
                'anesthetic_agent',
                'anesthetic_dosage',
                'anesthetic_technique',
                'tools_used_json',
                'materials_used_json',
                'hemostasis_achieved',
                'chairside_notes',
            ]);
        });
    }
};
