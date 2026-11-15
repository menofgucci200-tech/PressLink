<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ramassage ouvert à la zone : le client n'a plus besoin d'être client d'un
 * pressing. Il choisit un pressing de sa commune, ou laisse la demande au
 * premier pressing de la zone qui l'accepte (pressing_id vide jusque-là).
 * Un pressing peut masquer chez lui une demande qu'il ne prendra pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('pressing_id')->nullable()->change();
            $table->index(['status', 'commune']);
        });

        Schema::create('pickup_request_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['pickup_request_id', 'pressing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_request_dismissals');

        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'commune']);
            $table->unsignedBigInteger('pressing_id')->nullable(false)->change();
        });
    }
};
