<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ouverture exceptionnelle décidée par l'administrateur en dehors des
 * horaires (client arrivé après la fermeture, jour de fête…) : jusqu'à quand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pressings', function (Blueprint $table) {
            $table->timestamp('exceptional_open_until')->nullable()->after('opening_hours');
        });
    }

    public function down(): void
    {
        Schema::table('pressings', function (Blueprint $table) {
            $table->dropColumn('exceptional_open_until');
        });
    }
};
