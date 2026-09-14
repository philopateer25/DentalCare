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
        Schema::table('medical_histories', function (Blueprint $table) {
            $table->boolean('glaucoma_history')->default(false);
            $table->boolean('cataract_history')->default(false);
            $table->boolean('dry_eye_syndrome')->default(false);
            $table->boolean('wears_contact_lenses')->default(false);
            $table->boolean('vision_correction_history')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_histories', function (Blueprint $table) {
            $table->dropColumn([
                'glaucoma_history',
                'cataract_history',
                'dry_eye_syndrome',
                'wears_contact_lenses',
                'vision_correction_history',
            ]);
        });
    }
};
