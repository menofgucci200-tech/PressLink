<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audience du site vitrine (pages vues et clics), mesurée par PressLink
 * lui-même : ni cookie ni service extérieur. L'adresse IP n'est jamais
 * gardée : « visitor » est une empreinte qui change chaque jour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->string('path');
            $table->string('label', 120)->nullable();
            $table->string('target')->nullable();
            $table->string('source', 80)->nullable();
            $table->boolean('is_entry')->default(false);
            $table->string('device', 12);
            $table->char('visitor', 16);
            $table->timestamp('occurred_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_events');
    }
};
