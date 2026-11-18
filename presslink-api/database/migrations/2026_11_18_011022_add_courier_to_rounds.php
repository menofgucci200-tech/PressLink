<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coursier chargé d'un ramassage ou d'une livraison : chaque coursier voit
 * ses passages et ceux qui ne sont attribués à personne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->foreignId('courier_id')->nullable()->after('responded_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('order_deliveries', function (Blueprint $table) {
            $table->foreignId('courier_id')->nullable()->after('handled_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('courier_id');
        });

        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('courier_id');
        });
    }
};
