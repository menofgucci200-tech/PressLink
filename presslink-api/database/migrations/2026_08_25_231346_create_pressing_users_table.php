<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot staff ↔ pressing (RB-08 : un employé n'accède qu'aux pressings
 * auxquels il est affecté).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pressing_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pressing_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pressing_users');
    }
};
