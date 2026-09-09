<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('practices') && !Schema::hasColumn('practices', 'locale')) {
            Schema::table('practices', function (Blueprint $table) {
                $table->string('locale', 10)->default('en')->after('timezone');
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('locale', 10)->nullable()->after('remember_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('practices') && Schema::hasColumn('practices', 'locale')) {
            Schema::table('practices', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
