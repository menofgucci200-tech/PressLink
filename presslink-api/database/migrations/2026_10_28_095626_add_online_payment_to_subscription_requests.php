<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement en ligne d'une demande d'abonnement (GeniusPay) : la transaction
 * en cours (référence, page de paiement, début) et le paiement confirmé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->string('payment_reference', 64)->nullable()->unique()->after('payment_link');
            $table->string('payment_checkout_url', 500)->nullable()->after('payment_reference');
            $table->timestamp('payment_started_at')->nullable()->after('payment_checkout_url');
            $table->timestamp('paid_online_at')->nullable()->after('payment_started_at');
            $table->string('payment_method', 40)->nullable()->after('paid_online_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropUnique(['payment_reference']);
            $table->dropColumn(['payment_reference', 'payment_checkout_url', 'payment_started_at', 'paid_online_at', 'payment_method']);
        });
    }
};
