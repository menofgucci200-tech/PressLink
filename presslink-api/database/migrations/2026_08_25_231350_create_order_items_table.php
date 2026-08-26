<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RB-04 : une commande possède au moins un article (validé en couche
 * applicative, cf. StoreOrderAction).
 * Le nom et le prix unitaire sont dupliqués (snapshot) depuis le service
 * au moment de la commande pour ne jamais changer rétroactivement une
 * facture déjà émise si le tarif du pressing évolue ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('unit_price_fcfa');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('subtotal_fcfa');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
