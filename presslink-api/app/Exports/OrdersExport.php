<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export CSV/Excel des commandes d'un pressing — respecte les mêmes
 * filtres que ceux affichés dans le dashboard/les commandes
 * (Pressing::filteredOrders) pour ne jamais diverger de ce que voit
 * le staff à l'écran.
 */
class OrdersExport implements Export, FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param  array{status?: ?string, search?: ?string, date_from?: ?string, date_to?: ?string}  $filters */
    public function __construct(
        private readonly Pressing $pressing,
        private readonly array $filters = [],
    ) {}

    /** @return Collection<int, Order> */
    public function collection(): Collection
    {
        return $this->pressing->filteredOrders($this->filters)->latest('dropped_off_at')->get();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['N° commande', 'Client', 'Téléphone', 'Statut', 'Total (FCFA)', 'Déposée le', 'Retrait prévu', 'Récupérée le'];
    }

    /**
     * @param  Order  $order
     * @return list<string|int>
     */
    public function map($order): array
    {
        return [
            $order->order_number,
            $order->customer->fullName(),
            $order->customer->phone,
            $order->status->label(),
            $order->total_fcfa,
            $order->dropped_off_at?->format('d/m/Y') ?? '',
            $order->expected_at?->format('d/m/Y') ?? '',
            $order->recovered_at?->format('d/m/Y') ?? '',
        ];
    }
}
