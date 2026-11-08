<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dépôt rapide : le nombre de pièces comptées au comptoir, et la commande
     * reste « à compléter » tant que le détail et les prix ne sont pas saisis.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('details_pending')->default(false)->after('status');
            $table->unsignedSmallInteger('declared_pieces')->nullable()->after('details_pending');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['details_pending', 'declared_pieces']);
        });
    }
};
