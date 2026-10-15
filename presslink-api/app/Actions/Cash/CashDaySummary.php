<?php

namespace App\Actions\Cash;

use App\Enums\PaymentMethod;
use App\Models\OrderPayment;
use App\Models\Pressing;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Ce que l'application a enregistré comme encaissements pour un pressing et
 * une journée : total par moyen de paiement et liste des paiements. Sert à
 * la clôture de caisse et à la vue Caisse du groupe.
 */
class CashDaySummary
{
    /**
     * @return array{payments: Collection<int, OrderPayment>, by_method: array<string, array{label: string, count: int, total: int}>, total: int, cash: int, count: int}
     */
    public static function for(Pressing $pressing, Carbon $day): array
    {
        $payments = OrderPayment::query()
            ->whereHas('order', fn ($q) => $q->withTrashed()->where('pressing_id', $pressing->id))
            ->whereBetween('paid_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->with(['order:id,order_number,customer_id,status', 'order.customer:id,first_name,last_name', 'recorder:id,name'])
            ->orderBy('paid_at')
            ->get();

        $byMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $rows = $payments->where('method', $method);
            if ($rows->isNotEmpty()) {
                $byMethod[$method->value] = ['label' => $method->label(), 'count' => $rows->count(), 'total' => (int) $rows->sum('amount_fcfa')];
            }
        }

        return [
            'payments' => $payments,
            'by_method' => $byMethod,
            'total' => (int) $payments->sum('amount_fcfa'),
            'cash' => (int) ($byMethod[PaymentMethod::Cash->value]['total'] ?? 0),
            'count' => $payments->count(),
        ];
    }
}
