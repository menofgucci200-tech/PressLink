<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement des demandes d'abonnement sans API de paiement : le montant à
 * payer, la durée (mois ou année) et le lien de paiement (Wave Business)
 * que l'équipe PressLink envoie au pressing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->string('cycle', 10)->nullable()->after('extra_orders');
            $table->unsignedInteger('amount_fcfa')->nullable()->after('cycle');
            $table->string('payment_link', 500)->nullable()->after('amount_fcfa');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropColumn(['cycle', 'amount_fcfa', 'payment_link']);
        });
    }
};
