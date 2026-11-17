<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commissions des commerciaux : une ligne par paiement de l'abonnement
 * (formule ou renouvellement) d'un pressing qu'ils ont apporté, au taux du
 * jour ; payée ensuite par l'équipe PressLink (relevé du mois).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pressing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            // Gardés tels quels : le relevé reste lisible si le pressing est supprimé.
            $table->string('pressing_name', 150);
            $table->string('label', 255);
            $table->unsignedInteger('base_fcfa');
            $table->unsignedTinyInteger('rate');
            $table->unsignedInteger('amount_fcfa');
            $table->timestamp('earned_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('payout_reference', 100)->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sales_agent_id', 'earned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commissions');
    }
};
