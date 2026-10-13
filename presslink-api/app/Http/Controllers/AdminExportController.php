<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Livewire\Admin\Clients\Index as ClientsIndex;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Livewire\Admin\Pressings\Index as PressingsIndex;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV des listes de la console Super Admin (pressings, commandes,
 * clients), avec les mêmes filtres que la page affichée. Séparateur « ; »
 * et BOM UTF-8 pour une ouverture directe dans Excel en français.
 */
class AdminExportController extends Controller
{
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $filters = array_map(fn ($value) => is_string($value) ? $value : '', $request->query());

        [$query, $header, $row] = match ($type) {
            'pressings' => $this->pressings($filters),
            'orders' => $this->orders($filters),
            'clients' => $this->clients($filters),
            default => abort(404),
        };

        $filename = $type.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $header, $row) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            $query->chunk(500, function ($models) use ($out, $row) {
                foreach ($models as $model) {
                    fputcsv($out, $row($model), ';');
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{0: Builder<Pressing>, 1: list<string>, 2: callable(Pressing): list<mixed>}
     */
    private function pressings(array $filters): array
    {
        return [
            PressingsIndex::filteredQuery($filters),
            ['Code', 'Nom', 'Ville', 'Téléphone', 'Statut', 'Formule', 'Abonnement', 'Fin d\'essai', 'Équipe', 'Clients', 'Commandes', 'Commandes '.PressingsIndex::RECENT_DAYS.' j', 'Dernière commande', 'Créé le'],
            fn (Pressing $p) => [
                $p->code, $p->name, $p->city, $p->phone, $p->status->label(),
                $p->subscription?->plan->label(), $p->subscription?->status->label(), $p->subscription?->trial_ends_at?->format('d/m/Y'),
                $p->staff_count, $p->customers_count, $p->orders_count, $p->recent_orders_count,
                $p->last_order_at ? Carbon::parse($p->last_order_at)->format('d/m/Y') : '', $p->created_at->format('d/m/Y'),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{0: Builder<Order>, 1: list<string>, 2: callable(Order): list<mixed>}
     */
    private function orders(array $filters): array
    {
        return [
            OrdersIndex::filteredQuery($filters)->with(['pressing:id,name,code', 'customer:id,first_name,last_name,phone'])->withCount('items')->latest()->orderByDesc('id'),
            ['N°', 'Pressing', 'Code pressing', 'Client', 'Téléphone', 'Statut', 'Articles', 'Total (FCFA)', 'Créée le', 'Prévue le', 'Récupérée le'],
            fn (Order $o) => [
                $o->order_number, $o->pressing->name, $o->pressing->code, $o->customer->fullName(), $o->customer->phone,
                $o->status instanceof OrderStatus ? $o->status->label() : $o->status, $o->items_count, $o->total_fcfa,
                $o->created_at->format('d/m/Y H:i'), $o->expected_at?->format('d/m/Y'), $o->recovered_at?->format('d/m/Y'),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{0: Builder<Customer>, 1: list<string>, 2: callable(Customer): list<mixed>}
     */
    private function clients(array $filters): array
    {
        return [
            ClientsIndex::filteredQuery($filters),
            ['Prénom', 'Nom', 'Téléphone', 'Email', 'Pressings', 'Commandes', 'Montant (FCFA)', 'Dernière commande', 'Inscrit le'],
            fn (Customer $c) => [
                $c->first_name, $c->last_name, $c->phone, $c->email, $c->pressings->pluck('name')->implode(', '),
                $c->orders_count, (int) $c->total_spent,
                $c->last_order_at ? Carbon::parse($c->last_order_at)->format('d/m/Y') : '', $c->created_at->format('d/m/Y'),
            ],
        ];
    }
}
