<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Somme des paiements enregistrés, gardée sur la commande pour filtrer
     * et afficher « reste à payer » sans agréger les paiements à chaque
     * liste. Les commandes déjà récupérées avant ce suivi sont reprises
     * comme payées en espèces au retrait (l'hypothèse du tableau de bord
     * jusqu'ici), pour que « Encaissé » garde son historique.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('amount_paid_fcfa')->default(0)->after('total_fcfa');
        });

        $now = now();

        DB::table('order_payments')->insertUsing(
            ['order_id', 'amount_fcfa', 'method', 'note', 'recorded_by', 'paid_at', 'created_at', 'updated_at'],
            DB::table('orders')
                ->where('status', 'recuperee')
                ->where('total_fcfa', '>', 0)
                ->select('id', 'total_fcfa')
                ->selectRaw("'especes'")
                ->selectRaw("'Réglée au retrait (reprise de l''historique)'")
                ->addSelect('recovered_by')
                ->selectRaw('COALESCE(recovered_at, updated_at)')
                ->selectRaw('?', [$now])
                ->selectRaw('?', [$now]),
        );

        DB::table('orders')
            ->where('status', 'recuperee')
            ->update(['amount_paid_fcfa' => DB::raw('total_fcfa')]);
    }

    public function down(): void
    {
        DB::table('order_payments')
            ->whereIn('order_id', fn (Builder $q) => $q->select('id')->from('orders')->where('status', 'recuperee'))
            ->where('note', 'like', '%reprise de l%')
            ->delete();

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('amount_paid_fcfa');
        });
    }
};
