<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conditions et règlement du pressing (visibles par les clients et sur les
 * reçus) et droit de magasinage des commandes prêtes non retirées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pressings', function (Blueprint $table) {
            $table->text('terms')->nullable()->after('description');
            $table->json('storage_fee')->nullable()->after('terms');
        });
    }

    public function down(): void
    {
        Schema::table('pressings', function (Blueprint $table) {
            $table->dropColumn(['terms', 'storage_fee']);
        });
    }
};
