<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annulation encadrée : motif obligatoire (et note), gardé sur la commande et
 * dans l'historique des statuts. Un remboursement est un paiement négatif,
 * pour que la caisse du jour et les totaux encaissés restent justes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cancellation_reason', 30)->nullable()->after('notes');
            $table->string('cancellation_note', 500)->nullable()->after('cancellation_reason');
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('changed_by');
        });

        Schema::table('order_payments', function (Blueprint $table) {
            $table->integer('amount_fcfa')->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->unsignedInteger('amount_fcfa')->change();
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'cancellation_note']);
        });
    }
};
