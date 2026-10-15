<?php

namespace App\Exports;

use App\Models\OrderPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Paiements encaissés dans les pressings du groupe sur une période (journal de caisse). */
class GroupPaymentsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param  list<int>  $pressingIds */
    public function __construct(private readonly array $pressingIds, private readonly Carbon $start, private readonly Carbon $end) {}

    /** @return Collection<int, OrderPayment> */
    public function collection(): Collection
    {
        return OrderPayment::query()
            ->whereHas('order', fn ($q) => $q->withTrashed()->whereIn('pressing_id', $this->pressingIds))
            ->whereBetween('paid_at', [$this->start, $this->end])
            ->with(['order' => fn ($q) => $q->withTrashed()->with(['pressing:id,name', 'customer:id,first_name,last_name,phone']), 'recorder:id,name'])
            ->orderBy('paid_at')
            ->get();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Date', 'Heure', 'Pressing', 'N° commande', 'Client', 'Moyen de paiement', 'Montant (FCFA)', 'Encaissé par', 'Note'];
    }

    /**
     * @param  OrderPayment  $payment
     * @return list<string|int>
     */
    public function map($payment): array
    {
        return [
            $payment->paid_at->format('d/m/Y'),
            $payment->paid_at->format('H:i'),
            $payment->order?->pressing?->name ?? '',
            $payment->order?->order_number ?? '',
            $payment->order?->customer?->fullName() ?? '',
            $payment->method->label(),
            $payment->amount_fcfa,
            $payment->recorder?->name ?? '',
            $payment->note ?? '',
        ];
    }
}
