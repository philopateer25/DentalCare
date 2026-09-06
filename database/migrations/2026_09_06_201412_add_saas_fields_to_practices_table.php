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
            $table->string('license_key')->nullable()->unique()->after('is_active');
            $table->string('license_status')->default('active')->after('license_key');
            $table->json('features')->nullable()->after('license_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            $table->dropColumn(['license_key', 'license_status', 'features']);
        });
    }
};
