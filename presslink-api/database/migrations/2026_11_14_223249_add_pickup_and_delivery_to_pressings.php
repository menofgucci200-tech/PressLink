<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ramassage et livraison (formule Business et au-dessus, ou en option) :
 * réglages du pressing et communes qu'il dessert. Les clients pourront
 * envoyer leurs commandes depuis l'appli aux pressings de leur commune.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pressings', function (Blueprint $table) {
            $table->boolean('pickup_enabled')->default(false);
            $table->boolean('delivery_enabled')->default(false);
            $table->unsignedInteger('pickup_fee_fcfa')->default(0);
            $table->unsignedInteger('delivery_fee_fcfa')->default(0);
            $table->unsignedInteger('delivery_free_from_fcfa')->nullable();
            $table->json('delivery_slots')->nullable();
            $table->unsignedSmallInteger('turnaround_hours')->nullable();
        });

        Schema::create('pressing_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->string('commune', 60)->index();
            $table->unique(['pressing_id', 'commune']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pressing_service_areas');

        Schema::table('pressings', function (Blueprint $table) {
            $table->dropColumn(['pickup_enabled', 'delivery_enabled', 'pickup_fee_fcfa', 'delivery_fee_fcfa', 'delivery_free_from_fcfa', 'delivery_slots', 'turnaround_hours']);
        });
    }
};
