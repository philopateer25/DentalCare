<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('features');
            $table->unsignedTinyInteger('onboarding_step')->default(1)->after('onboarding_completed_at');
        });

        // Existing practices in database should be marked as onboarded so they are not forced through setup unexpectedly.
        DB::table('practices')->update([
            'onboarding_completed_at' => now(),
            'onboarding_step' => 6,
        ]);
    }

    public function down(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            $table->dropColumn(['onboarding_completed_at', 'onboarding_step']);
        });
    }
};
