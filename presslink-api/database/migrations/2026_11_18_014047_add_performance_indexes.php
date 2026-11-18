<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index des pages les plus visitées (mesurés sur ~10 000 commandes) :
 * tableau de bord (commandes du jour, prêtes dans la période), dernières
 * commandes d'un pressing et de la console, liste des clients par date
 * d'arrivée. Sans eux, MySQL lit et trie toutes les commandes du pressing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['pressing_id', 'created_at']);
            $table->index(['pressing_id', 'dropped_off_at']);
            $table->index('created_at');
        });

        // Remplace aussi l'index de la clé étrangère order_id (MySQL le retire de lui-même).
        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->index(['order_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('pressing_customers', function (Blueprint $table) {
            $table->index(['pressing_id', 'joined_at', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pressing_customers', function (Blueprint $table) {
            $table->dropIndex(['pressing_id', 'joined_at', 'customer_id']);
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            // La clé étrangère a besoin d'un index sur order_id avant qu'on retire le composite.
            $table->index('order_id', 'order_status_histories_order_id_foreign');
            $table->dropIndex(['order_id', 'status', 'created_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['pressing_id', 'created_at']);
            $table->dropIndex(['pressing_id', 'dropped_off_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
