<?php

namespace App\Actions\Pressings;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\CashClosing;
use App\Models\CashExpense;
use App\Models\Customer;
use App\Models\OfflineOperation;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Efface une partie des données d'un pressing sans supprimer le pressing
 * (console Super Admin) : par exemple les commandes et les clients d'essai
 * saisis avant le vrai démarrage. Le pressing, son équipe, ses réglages et
 * son abonnement restent. L'effacement est inscrit au journal du pressing.
 */
class ErasePressingData
{
    public const ORDERS = 'commandes';

    public const CUSTOMERS = 'clients';

    public const CASH = 'caisse';

    public const CATALOG = 'articles';

    public const ACTIVITY = 'journal';

    /** @var array<string, string> partie => libellé */
    public const PARTS = [
        self::ORDERS => 'Commandes (avec leurs paiements, historiques et signalements)',
        self::CUSTOMERS => 'Clients (retirés du fichier du pressing)',
        self::CASH => 'Caisse (clôtures et dépenses)',
        self::CATALOG => 'Articles et prix',
        self::ACTIVITY => 'Journal d\'activité',
    ];

    /**
     * @param  list<string>  $parts  clés de PARTS ; effacer les clients efface aussi les commandes
     * @return array<string, int> partie => nombre d'éléments effacés
     */
    public function handle(Pressing $pressing, array $parts): array
    {
        $parts = array_values(array_intersect(array_keys(self::PARTS), $parts));
        if (in_array(self::CUSTOMERS, $parts, true) && ! in_array(self::ORDERS, $parts, true)) {
            // Un client ne quitte pas le fichier en laissant ses commandes derrière lui.
            array_unshift($parts, self::ORDERS);
        }

        $counts = ActivityLog::withoutLogging(fn () => DB::transaction(function () use ($pressing, $parts) {
            $counts = [];

            if (in_array(self::ORDERS, $parts, true)) {
                $counts[self::ORDERS] = $this->eraseOrders($pressing);
            }
            if (in_array(self::CUSTOMERS, $parts, true)) {
                $counts[self::CUSTOMERS] = $this->eraseCustomers($pressing);
            }
            if (in_array(self::CASH, $parts, true)) {
                $counts[self::CASH] = CashClosing::where('pressing_id', $pressing->id)->delete()
                    + CashExpense::where('pressing_id', $pressing->id)->delete();
            }
            if (in_array(self::CATALOG, $parts, true)) {
                $counts[self::CATALOG] = Service::where('pressing_id', $pressing->id)->delete();
            }
            if (in_array(self::ACTIVITY, $parts, true)) {
                $counts[self::ACTIVITY] = ActivityLog::where('pressing_id', $pressing->id)->delete();
            }

            return $counts;
        }));

        if ($counts !== []) {
            $summary = collect($counts)->map(fn (int $count, string $part) => "{$part} ({$count})")->implode(', ');
            ActivityLog::record($pressing->id, ActivityAction::DataErased, "Données effacées par l'équipe PressLink : {$summary}", null, ['erased' => $counts]);
        }

        return $counts;
    }

    /**
     * Commandes du pressing (articles, historique, paiements et signalements
     * suivent en cascade), notifications des clients qui en parlent,
     * opérations hors connexion, et quota du mois remis à zéro.
     */
    private function eraseOrders(Pressing $pressing): int
    {
        $count = 0;

        Order::where('pressing_id', $pressing->id)->select('id')->chunkById(500, function ($orders) use (&$count) {
            $ids = $orders->pluck('id');
            DB::table('notifications')
                ->where('notifiable_type', (new Customer)->getMorphClass())
                ->whereIn('data->order_id', $ids)
                ->delete();
            $count += Order::whereKey($ids)->delete();
        });

        OfflineOperation::where('pressing_id', $pressing->id)->delete();
        $pressing->subscription?->update(['orders_used' => 0]);

        return $count;
    }

    /**
     * Retire tous les clients du fichier du pressing. Une fiche client qui ne
     * sert plus nulle part (aucun autre pressing, pas de compte dans
     * l'application, plus aucune commande) est supprimée.
     */
    private function eraseCustomers(Pressing $pressing): int
    {
        $customerIds = $pressing->customers()->pluck('customers.id');
        $pressing->customers()->detach();

        Customer::whereKey($customerIds)
            ->whereNull('password')
            ->whereDoesntHave('pressings')
            ->whereDoesntHave('orders')
            ->each(function (Customer $customer) {
                DB::table('notifications')
                    ->where('notifiable_type', $customer->getMorphClass())
                    ->where('notifiable_id', $customer->id)
                    ->delete();
                $customer->delete();
            });

        return $customerIds->count();
    }
}
