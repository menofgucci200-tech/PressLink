<?php

namespace App\Exports;

use App\Livewire\Group\Orders;
use App\Models\Order;
use App\Support\SpreadsheetSafe;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Commandes de plusieurs pressings du groupe, avec la colonne Pressing (pour le comptable). */
class GroupOrdersExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  list<int>  $pressingIds
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private readonly array $pressingIds, private readonly array $filters = []) {}

    /** @return Collection<int, Order> */
    public function collection(): Collection
    {
        return Orders::query($this->pressingIds, $this->filters)->latest('dropped_off_at')->get();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Pressing', 'N° commande', 'Client', 'Téléphone', 'Statut', 'Total (FCFA)', 'Payé (FCFA)', 'Reste à payer (FCFA)', 'Paiement', 'Déposée le', 'Retrait prévu', 'Récupérée le'];
    }

    /**
     * @param  Order  $order
     * @return list<string|int>
     */
    public function map($order): array
    {
        return SpreadsheetSafe::row([
            $order->pressing->name,
            $order->order_number,
            $order->customer->fullName(),
            $order->customer->phone,
            $order->status->label(),
            $order->total_fcfa,
            $order->amount_paid_fcfa,
            $order->balance_due_fcfa,
            $order->payment_status_label,
            $order->dropped_off_at?->format('d/m/Y') ?? '',
            $order->expected_at?->format('d/m/Y') ?? '',
            $order->recovered_at?->format('d/m/Y') ?? '',
        ]);
    }
}
