<?php

use App\Actions\Demo\ResetDemoPressing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pressings de test (« Découvrir avec un pressing fictif ») :
 * - `is_demo` : pressing exclu des statistiques (démo DM-0001 et tests) ;
 * - `sandbox_expires_at` : pressing de test d'un visiteur, supprimé à cette date ;
 * - `customers.sandbox_pressing_id` : client fictif d'un pressing de test (0
 *   pour un vrai client). Le numéro n'est unique qu'au sein d'un même
 *   pressing de test : un visiteur peut saisir le numéro d'un vrai client
 *   sans jamais toucher à sa fiche ;
 * - `sandbox_trials` : chaque test lancé (contact du gérant), conservé après
 *   la suppression du pressing de test pour le suivi commercial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pressings', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('status');
            $table->timestamp('sandbox_expires_at')->nullable()->after('is_demo')->index();
        });

        DB::table('pressings')->where('code', ResetDemoPressing::PRESSING_CODE)->update(['is_demo' => true]);

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('sandbox_pressing_id')->default(0)->after('phone');
            $table->dropUnique(['phone']);
            $table->unique(['phone', 'sandbox_pressing_id']);
        });

        Schema::create('sandbox_trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pressing_name');
            $table->string('contact_phone', 20);
            $table->string('ip_address', 45)->nullable();
            // Valeur par défaut explicite : sans elle, MariaDB remettrait la date à jour à chaque modification de la ligne.
            $table->timestamp('expires_at')->useCurrent();
            $table->foreignId('converted_pressing_id')->nullable()->constrained('pressings')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_trials');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['phone', 'sandbox_pressing_id']);
            $table->unique('phone');
            $table->dropColumn('sandbox_pressing_id');
        });

        Schema::table('pressings', function (Blueprint $table) {
            $table->dropColumn(['is_demo', 'sandbox_expires_at']);
        });
    }
};
