<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historique de tous les paiements reçus par PressLink, réussis comme
 * échoués : chaque tentative de paiement en ligne (GeniusPay) et chaque
 * paiement enregistré à la main par l'équipe, avec sa référence PressLink
 * (PL-AAMMJJ-XXXXX), pour les taux de réussite par moyen et par pressing.
 * Les paiements déjà reçus sont repris (références PL-AAMMJJ-D0001…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('subscription_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pressing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pressing_name');
            $table->string('label')->nullable();
            $table->string('channel', 10);
            $table->string('payment_method', 30)->nullable();
            $table->string('status', 12);
            $table->unsignedInteger('amount_fcfa');
            $table->unsignedInteger('fee_fcfa')->default(0);
            $table->unsignedInteger('total_fcfa');
            $table->unsignedInteger('paid_amount_fcfa')->nullable();
            $table->unsignedInteger('gateway_fees_fcfa')->nullable();
            $table->unsignedInteger('net_received_fcfa')->nullable();
            $table->string('gateway_reference', 100)->nullable()->index();
            $table->string('environment', 10)->nullable();
            $table->string('failure_reason')->nullable();
            // Valeur par défaut explicite : sans elle, MariaDB remettrait la date à jour à chaque modification de la ligne.
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });

        // Paiements déjà reçus (en ligne ou enregistrés par l'équipe).
        DB::table('subscription_requests')
            ->leftJoin('pressings', 'pressings.id', '=', 'subscription_requests.pressing_id')
            ->where('subscription_requests.status', 'done')
            ->where('subscription_requests.amount_fcfa', '>', 0)
            ->orderBy('subscription_requests.id')
            ->select('subscription_requests.*', 'pressings.name as pressing_name')
            ->get()
            ->each(function ($request) {
                $online = $request->paid_online_at !== null;
                $completedAt = $online ? $request->paid_online_at : ($request->handled_at ?? $request->updated_at);

                DB::table('payment_attempts')->insert([
                    'reference' => 'PL-'.date('ymd', strtotime((string) $completedAt)).'-D'.str_pad((string) $request->id, 4, '0', STR_PAD_LEFT),
                    'subscription_request_id' => $request->id,
                    'pressing_id' => $request->pressing_id,
                    'pressing_name' => $request->pressing_name ?? 'Pressing supprimé',
                    'channel' => $online ? 'online' : 'manual',
                    'payment_method' => $online ? $request->payment_method : null,
                    'status' => 'paid',
                    'amount_fcfa' => $request->amount_fcfa,
                    'fee_fcfa' => $online ? (int) $request->payment_fee_fcfa : 0,
                    'total_fcfa' => $online ? (int) ($request->paid_amount_fcfa ?? $request->amount_fcfa) : $request->amount_fcfa,
                    'paid_amount_fcfa' => $online ? ($request->paid_amount_fcfa ?? $request->amount_fcfa) : $request->amount_fcfa,
                    'gateway_fees_fcfa' => $online ? $request->gateway_fees_fcfa : null,
                    'net_received_fcfa' => $online ? $request->net_received_fcfa : $request->amount_fcfa,
                    'gateway_reference' => $online ? $request->payment_reference : null,
                    'started_at' => $request->payment_started_at ?? $completedAt,
                    'completed_at' => $completedAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
