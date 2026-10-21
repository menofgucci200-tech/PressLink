<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opérations faites sur le comptoir hors connexion (client, commande,
 * paiement, statut), une ligne par opération reçue : le même envoi reçu deux
 * fois (coupure pendant l'envoi, deux onglets) n'est jamais appliqué deux fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->json('result');
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_operations');
    }
};
