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
        Schema::table('practices', function (Blueprint $table) {
            if (!Schema::hasColumn('practices', 'package_tier')) {
                $table->string('package_tier')->default('amigo_ultra')->after('license_status');
            }
            if (!Schema::hasColumn('practices', 'chair_limit')) {
                $table->unsignedInteger('chair_limit')->default(5)->after('package_tier');
            }
            if (!Schema::hasColumn('practices', 'xray_limit')) {
                $table->unsignedInteger('xray_limit')->default(3)->after('chair_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            $table->dropColumn(['package_tier', 'chair_limit', 'xray_limit']);
        });
    }
};
