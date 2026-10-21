<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quota de commandes MENSUEL : on note le mois (« 2026-10 ») auquel se
 * rapporte `orders_used`. Un mois différent = compteur reparti de zéro, sans
 * tâche planifiée à attendre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->char('orders_month', 7)->nullable()->after('orders_used');
        });

        DB::table('subscriptions')->update(['orders_month' => now()->format('Y-m')]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('orders_month');
        });
    }
};
