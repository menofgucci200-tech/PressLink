<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot client ↔ pressing (un client peut rejoindre plusieurs pressings
 * via le code pressing — Cahier §11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pressing_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['pressing_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pressing_customers');
    }
};
