<?php

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RB-09 : un pressing dont l'abonnement est expiré ne peut plus créer
 * de commande. Modèle économique §2 (Starter/Pro/Business/Enterprise).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->string('plan')->default(SubscriptionPlan::Starter->value);
            $table->string('status')->default(SubscriptionStatus::Trialing->value);
            $table->string('billing_cycle')->default('monthly');
            $table->unsignedInteger('orders_limit')->nullable();
            $table->unsignedInteger('orders_used')->default(0);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
