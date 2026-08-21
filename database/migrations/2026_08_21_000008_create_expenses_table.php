<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->enum('category', ['lab_fees', 'consumables', 'salaries', 'rent', 'utilities', 'maintenance', 'other'])->default('other');
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->string('receipt_attachment')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('logged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
