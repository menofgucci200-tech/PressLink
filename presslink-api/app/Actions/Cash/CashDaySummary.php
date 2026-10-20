<?php

namespace App\Actions\Cash;

use App\Enums\PaymentMethod;
use App\Models\CashExpense;
use App\Models\OrderPayment;
use App\Models\Pressing;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Ce que l'application a enregistré pour un pressing et une journée :
 * encaissements par moyen de paiement, dépenses de caisse et espèces
 * attendues. Sert à la clôture de caisse et à la vue Caisse du groupe.
 */
class CashDaySummary
{
    /**
     * @return array{payments: Collection<int, OrderPayment>, by_method: array<string, array{label: string, count: int, total: int}>, total: int, cash: int, count: int, expenses: Collection<int, CashExpense>, expenses_total: int, expected_cash: int}
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

        $expenses = CashExpense::query()
            ->where('pressing_id', $pressing->id)
            ->whereDate('business_date', $day)
            ->with('recorder:id,name')
            ->orderBy('created_at')
            ->get();
        $cash = (int) ($byMethod[PaymentMethod::Cash->value]['total'] ?? 0);
        $expensesTotal = (int) $expenses->sum('amount_fcfa');

        return [
            'expenses' => $expenses,
            'expenses_total' => $expensesTotal,
            // Ce qui doit se trouver dans la caisse : espèces encaissées − dépenses.
            'expected_cash' => $cash - $expensesTotal,
            'payments' => $payments,
            'by_method' => $byMethod,
            'total' => (int) $payments->sum('amount_fcfa'),
            'cash' => (int) ($byMethod[PaymentMethod::Cash->value]['total'] ?? 0),
            'count' => $payments->count(),
        ];
    }
}
