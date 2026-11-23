<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — each clinic's own dropdown lists ("type it once, pick it next
 * time"): medications, dosages, conditions, expense categories, note phrases…
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->string('list', 60);
            $table->string('value', 255);
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['practice_id', 'list', 'value']);
            $table->index(['practice_id', 'list', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_options');
    }
};
