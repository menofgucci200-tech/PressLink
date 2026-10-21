<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Options achetées en plus de la formule : fonctions à la carte (exports,
 * caisse complète…) et commandes supplémentaires pour le mois en cours ;
 * plus les demandes faites depuis la page Abonnement, traitées par l'équipe
 * PressLink (paiement hors application).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('addons')->nullable()->after('orders_month');
            $table->unsignedInteger('extra_orders')->default(0)->after('addons');
            $table->char('extra_orders_month', 7)->nullable()->after('extra_orders');
        });

        Schema::create('subscription_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20);
            $table->string('feature', 20)->nullable();
            $table->string('plan', 20)->nullable();
            $table->unsignedInteger('extra_orders')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_requests');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['addons', 'extra_orders', 'extra_orders_month']);
        });
    }
};
