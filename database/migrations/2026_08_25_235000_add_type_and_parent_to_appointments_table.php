<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->enum('type', ['new_visit', 'follow_up'])->default('new_visit')->after('status');
            $table->foreignId('parent_appointment_id')->nullable()->after('type')->constrained('appointments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['parent_appointment_id']);
            $table->dropColumn(['type', 'parent_appointment_id']);
        });
    }
};
