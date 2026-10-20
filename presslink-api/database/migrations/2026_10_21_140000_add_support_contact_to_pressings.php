<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact du pressing pour ses clients (téléphone, WhatsApp, e-mail,
 * disponibilités) : affiché sur la fiche du pressing et sous chaque commande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pressings', function (Blueprint $table) {
            $table->json('support_contact')->nullable()->after('storage_fee');
        });
    }

    public function down(): void
    {
        Schema::table('pressings', function (Blueprint $table) {
            $table->dropColumn('support_contact');
        });
    }
};
