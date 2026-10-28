<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frais d'un paiement en ligne : frais de l'opérateur ajoutés au prix et
 * payés par le pressing, montant réellement payé, frais retenus par
 * GeniusPay et montant net reçu par PressLink.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->unsignedInteger('payment_fee_fcfa')->nullable()->after('payment_method');
            $table->unsignedInteger('paid_amount_fcfa')->nullable()->after('payment_fee_fcfa');
            $table->unsignedInteger('gateway_fees_fcfa')->nullable()->after('paid_amount_fcfa');
            $table->unsignedInteger('net_received_fcfa')->nullable()->after('gateway_fees_fcfa');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropColumn(['payment_fee_fcfa', 'paid_amount_fcfa', 'gateway_fees_fcfa', 'net_received_fcfa']);
        });
    }
};
