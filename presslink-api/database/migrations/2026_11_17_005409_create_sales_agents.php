<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commerciaux de terrain : chacun a son compte, son code d'inscription et
 * les pressings qu'il a apportés ; les signalements « ce pressing vient de
 * moi » attendent la validation du Super Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            // Null = taux commun réglé dans la console (20 % par défaut).
            $table->unsignedTinyInteger('commission_rate')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::table('pressings', function (Blueprint $table) {
            $table->foreignId('sales_agent_id')->nullable()->after('sandbox_expires_at')->constrained()->nullOnDelete();
            $table->timestamp('sales_agent_attached_at')->nullable()->after('sales_agent_id');
        });

        Schema::create('sales_agent_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_agent_id')->constrained()->cascadeOnDelete();
            // Pressing reconnu par son numéro au moment du signalement (sinon choisi par le Super Admin).
            $table->foreignId('pressing_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('pressing_name', 150);
            $table->string('phone', 20);
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_agent_claims');
        Schema::table('pressings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_agent_id');
            $table->dropColumn('sales_agent_attached_at');
        });
        Schema::dropIfExists('sales_agents');
    }
};
