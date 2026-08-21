<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tax_id')->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->string('timezone')->default('Africa/Cairo');
            $table->string('logo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('operatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "Chair 1 - Ortho", "Operatory A"
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('practice_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('practice_id')->constrained()->nullOnDelete();
            $table->string('role')->default('dentist')->after('email'); // admin, practice_manager, dentist, hygienist, receptionist, accountant
            $table->string('phone')->nullable()->after('role');
        });

        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->string('specialty')->default('General Dentistry'); // Orthodontics, Endodontics, Periodontics, Prosthodontics, Oral Surgery, Pediatric
            $table->string('license_number')->nullable();
            $table->decimal('default_commission_percentage', 5, 2)->default(40.00); // e.g. 40.00% split to doctor
            $table->text('bio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['practice_id']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['practice_id', 'branch_id', 'role', 'phone']);
        });
        Schema::dropIfExists('operatories');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('practices');
    }
};
