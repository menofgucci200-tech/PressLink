<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Services d'un pressing (Repassage, Nettoyage…) et prix de chaque pièce
 * (Chemise, Robe…) selon le service.
 *
 * Les « services » historiques (table `services`) sont en réalité des pièces
 * à prix unique : leurs prix et ceux de leurs variantes sont repris dans
 * `service_prices` sous le service « Nettoyage », créé avec « Repassage » pour
 * chaque pressing existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('icon', 40)->default('autre');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['pressing_id', 'position']);
        });

        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('prestation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_fcfa');
            $table->timestamps();

            $table->unique(['service_id', 'service_variant_id', 'prestation_id'], 'service_prices_unique');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('icon', 40)->nullable()->after('name');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('prestation_id')->nullable()->after('service_variant_id')->constrained()->nullOnDelete();
            $table->string('prestation_name', 60)->nullable()->after('prestation_id');
        });

        // Reprise de l'existant : deux services par pressing, prix rattachés au Nettoyage.
        DB::statement("INSERT INTO prestations (pressing_id, name, icon, position, is_active, created_at, updated_at) SELECT id, 'Repassage', 'repassage', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM pressings");
        DB::statement("INSERT INTO prestations (pressing_id, name, icon, position, is_active, created_at, updated_at) SELECT id, 'Nettoyage', 'nettoyage', 2, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM pressings");
        DB::statement("INSERT INTO service_prices (service_id, service_variant_id, prestation_id, price_fcfa, created_at, updated_at) SELECT s.id, NULL, p.id, s.price_fcfa, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM services s JOIN prestations p ON p.pressing_id = s.pressing_id AND p.name = 'Nettoyage'");
        DB::statement("INSERT INTO service_prices (service_id, service_variant_id, prestation_id, price_fcfa, created_at, updated_at) SELECT v.service_id, v.id, p.id, v.price_fcfa, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM service_variants v JOIN services s ON s.id = v.service_id JOIN prestations p ON p.pressing_id = s.pressing_id AND p.name = 'Nettoyage'");
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prestation_id');
            $table->dropColumn('prestation_name');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('icon');
        });

        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('prestations');
    }
};
