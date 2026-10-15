<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clôture de caisse journalière d'un pressing : ce que l'application a
     * enregistré (par moyen de paiement) et les espèces comptées par le
     * gérant, avec l'écart.
     */
    public function up(): void
    {
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->json('totals_by_method');
            $table->unsignedInteger('total_fcfa');
            $table->unsignedInteger('expected_cash_fcfa');
            $table->unsignedInteger('declared_cash_fcfa');
            $table->integer('difference_fcfa');
            $table->unsignedInteger('payments_count');
            $table->string('note', 500)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pressing_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
    }
};
