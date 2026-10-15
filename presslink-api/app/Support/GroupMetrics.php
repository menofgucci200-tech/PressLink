<?php

namespace App\Support;

use App\Enums\OrderIssueStatus;
use App\Enums\OrderStatus;
use App\Models\CashClosing;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\Pressing;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs d'un groupe de pressings (ceux qu'un propriétaire ou un
 * responsable de zone administre) sur une période : totaux, comparatif par
 * pressing, alertes et évolution de l'encaissé. Une requête groupée par
 * indicateur, quel que soit le nombre de pressings.
 */
class GroupMetrics
{
    /** Sans commande depuis ce nombre de jours, un pressing est signalé. */
    public const DORMANT_AFTER_DAYS = 3;

    /** @var list<int> */
    private array $ids;

    /** @param  Collection<int, Pressing>  $pressings */
    public function __construct(
        public readonly Collection $pressings,
        public readonly Carbon $start,
        public readonly Carbon $end,
    ) {
        $this->ids = $pressings->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Comparatif : une ligne par pressing.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function perPressing(): Collection
    {
        $length = (int) $this->start->diffInDays($this->end) + 1;
        $previousEnd = $this->start->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subDays($length - 1)->startOfDay();

        $orders = Order::whereIn('pressing_id', $this->ids)
            ->whereBetween('dropped_off_at', [$this->start, $this->end])
            ->selectRaw('pressing_id, COUNT(*) as orders_count')
            ->selectRaw("SUM(CASE WHEN status != 'annulee' THEN total_fcfa ELSE 0 END) as volume")
            ->selectRaw("SUM(CASE WHEN status != 'annulee' THEN 1 ELSE 0 END) as kept_count")
            ->groupBy('pressing_id')
            ->get()
            ->keyBy('pressing_id');

        $revenue = $this->revenueByPressing($this->start, $this->end);
        $previousRevenue = $this->revenueByPressing($previousStart, $previousEnd);

        $outstanding = Order::whereIn('pressing_id', $this->ids)
            ->where('status', '!=', OrderStatus::Annulee->value)
            ->whereColumn('amount_paid_fcfa', '<', 'total_fcfa')
            ->selectRaw('pressing_id, SUM(total_fcfa - amount_paid_fcfa) as due')
            ->groupBy('pressing_id')
            ->pluck('due', 'pressing_id');

        $late = Order::whereIn('pressing_id', $this->ids)
            ->whereIn('status', [OrderStatus::Recue->value, OrderStatus::Traitement->value, OrderStatus::Attente->value])
            ->whereDate('expected_at', '<', today())
            ->selectRaw('pressing_id, COUNT(*) as aggregate')
            ->groupBy('pressing_id')
            ->pluck('aggregate', 'pressing_id');

        $openIssues = DB::table('order_issues')
            ->join('orders', 'orders.id', '=', 'order_issues.order_id')
            ->whereIn('orders.pressing_id', $this->ids)
            ->where('order_issues.status', OrderIssueStatus::Open->value)
            ->selectRaw('orders.pressing_id, COUNT(*) as aggregate')
            ->groupBy('orders.pressing_id')
            ->pluck('aggregate', 'pressing_id');

        $readyOverdue = $this->readyOverdueByPressing();
        $turnaround = $this->turnaroundHoursByPressing();

        return $this->pressings->map(function (Pressing $pressing) use ($orders, $revenue, $previousRevenue, $outstanding, $late, $openIssues, $readyOverdue, $turnaround) {
            $row = $orders->get($pressing->id);
            $kept = (int) ($row->kept_count ?? 0);
            $current = (int) ($revenue[$pressing->id] ?? 0);
            $previous = (int) ($previousRevenue[$pressing->id] ?? 0);

            return [
                'pressing' => $pressing,
                'orders' => (int) ($row->orders_count ?? 0),
                'revenue' => $current,
                'revenue_change_pct' => $previous > 0 ? (int) round(($current - $previous) / $previous * 100) : null,
                'average_basket' => $kept > 0 ? (int) round(((int) $row->volume) / $kept) : 0,
                'outstanding' => (int) ($outstanding[$pressing->id] ?? 0),
                'turnaround_hours' => $turnaround[$pressing->id] ?? null,
                'late' => (int) ($late[$pressing->id] ?? 0),
                'ready_overdue' => (int) ($readyOverdue[$pressing->id] ?? 0),
                'open_issues' => (int) ($openIssues[$pressing->id] ?? 0),
            ];
        })->values();
    }

    /**
     * Totaux du groupe, à partir du comparatif.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int|null>
     */
    public function totals(Collection $rows): array
    {
        $statusCounts = Order::whereIn('pressing_id', $this->ids)
            ->whereIn('status', [OrderStatus::Recue->value, OrderStatus::Traitement->value, OrderStatus::Attente->value, OrderStatus::Prete->value])
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $kept = $rows->sum(fn ($r) => $r['average_basket'] > 0 ? $r['orders'] : 0);

        return [
            'orders' => $rows->sum('orders'),
            'revenue' => $rows->sum('revenue'),
            'outstanding' => $rows->sum('outstanding'),
            'in_progress' => (int) (($statusCounts[OrderStatus::Recue->value] ?? 0) + ($statusCounts[OrderStatus::Traitement->value] ?? 0) + ($statusCounts[OrderStatus::Attente->value] ?? 0)),
            'ready' => (int) ($statusCounts[OrderStatus::Prete->value] ?? 0),
            'late' => $rows->sum('late'),
            'ready_overdue' => $rows->sum('ready_overdue'),
            'open_issues' => $rows->sum('open_issues'),
            'average_basket' => $kept > 0 ? (int) round($rows->sum(fn ($r) => $r['average_basket'] * $r['orders']) / $kept) : 0,
        ];
    }

    /**
     * Points d'attention, du plus urgent au moins urgent. Chaque alerte
     * renvoie vers la bonne page du bon pressing.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{tone: string, pressing: Pressing, title: string, hint: string, url: string}>
     */
    public function alerts(Collection $rows): array
    {
        $alerts = [];
        $switch = fn (Pressing $p, string $to) => route('pressings.switch', ['pressing' => $p, 'vers' => $to]);
        $plural = fn (int $n, string $word) => $n.' '.$word.($n > 1 ? 's' : '');

        $lastOrderAt = Order::whereIn('pressing_id', $this->ids)
            ->selectRaw('pressing_id, MAX(created_at) as last_at')
            ->groupBy('pressing_id')
            ->pluck('last_at', 'pressing_id');

        $closedYesterday = CashClosing::whereIn('pressing_id', $this->ids)
            ->whereDate('business_date', today()->subDay())
            ->pluck('pressing_id')
            ->all();
        $usesCashClosing = CashClosing::whereIn('pressing_id', $this->ids)
            ->where('business_date', '>=', today()->subDays(30))
            ->distinct()
            ->pluck('pressing_id')
            ->all();
        $paidYesterday = OrderPayment::query()
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->whereIn('orders.pressing_id', $this->ids)
            ->whereDate('order_payments.paid_at', today()->subDay())
            ->distinct()
            ->pluck('orders.pressing_id')
            ->all();

        foreach ($rows as $row) {
            $p = $row['pressing'];

            if ($row['open_issues'] > 0) {
                $alerts[] = ['tone' => 'error', 'pressing' => $p, 'title' => $plural($row['open_issues'], 'signalement').' ouvert'.($row['open_issues'] > 1 ? 's' : ''), 'hint' => 'Des clients attendent une réponse.', 'url' => $switch($p, '/signalements')];
            }
            if ($row['late'] > 0) {
                $alerts[] = ['tone' => 'error', 'pressing' => $p, 'title' => $plural($row['late'], 'commande').' en retard', 'hint' => 'Date de retrait prévue dépassée.', 'url' => $switch($p, '/commandes')];
            }
            if ($row['ready_overdue'] > 0) {
                $alerts[] = ['tone' => 'warning', 'pressing' => $p, 'title' => $plural($row['ready_overdue'], 'commande').' prête'.($row['ready_overdue'] > 1 ? 's' : '').' depuis plus de '.Order::READY_REMINDER_DAYS.' jours', 'hint' => 'Relancer les clients.', 'url' => $switch($p, '/commandes?status=prete')];
            }
            if (in_array($p->id, $usesCashClosing, true) && in_array($p->id, $paidYesterday, true) && ! in_array($p->id, $closedYesterday, true)) {
                $alerts[] = ['tone' => 'warning', 'pressing' => $p, 'title' => 'Caisse d\'hier non clôturée', 'hint' => 'Des paiements ont été encaissés sans clôture.', 'url' => $switch($p, '/caisse')];
            }
            $last = isset($lastOrderAt[$p->id]) ? Carbon::parse($lastOrderAt[$p->id]) : null;
            if ($last && $last->lt(now()->subDays(self::DORMANT_AFTER_DAYS))) {
                $alerts[] = ['tone' => 'info', 'pressing' => $p, 'title' => 'Aucune commande depuis '.(int) $last->diffInDays(now()).' jours', 'hint' => 'Le pressing utilise-t-il toujours PressLink ?', 'url' => $switch($p, '/tableau-de-bord')];
            }
        }

        $order = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($alerts, fn ($a, $b) => $order[$a['tone']] <=> $order[$b['tone']]);

        return $alerts;
    }

    /**
     * Encaissé par jour et par pressing, pour le graphique d'évolution
     * (au moins les 7 derniers jours, même si la période est plus courte).
     *
     * @return array{days: list<string>, labels: list<string>, series: list<array{pressing: Pressing, values: list<int>}>, max: int}
     */
    public function dailyRevenue(): array
    {
        $start = $this->start->copy()->startOfDay();
        if ($start->diffInDays($this->end) < 6) {
            $start = $this->end->copy()->subDays(6)->startOfDay();
        }

        $rows = OrderPayment::query()
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->whereIn('orders.pressing_id', $this->ids)
            ->where('orders.status', '!=', OrderStatus::Annulee->value)
            ->whereNull('orders.deleted_at')
            ->whereBetween('order_payments.paid_at', [$start, $this->end])
            ->selectRaw('orders.pressing_id as pressing_id, DATE(order_payments.paid_at) as day, SUM(order_payments.amount_fcfa) as total')
            ->groupBy('orders.pressing_id', 'day')
            ->get();

        $days = [];
        for ($d = $start->copy(); $d->lte($this->end); $d->addDay()) {
            $days[] = $d->toDateString();
        }

        $series = $this->pressings->map(function (Pressing $p) use ($rows, $days) {
            $byDay = $rows->where('pressing_id', $p->id)->pluck('total', 'day');

            return ['pressing' => $p, 'values' => array_map(fn ($day) => (int) ($byDay[$day] ?? 0), $days)];
        })->values()->all();

        $max = max(1, ...array_map(fn ($s) => max($s['values'] ?: [0]), $series ?: [['values' => [0]]]));

        return [
            'days' => $days,
            'labels' => array_map(fn ($day) => Carbon::parse($day)->format('d/m'), $days),
            'series' => $series,
            'max' => $max,
        ];
    }

    /** @return Collection<int, int> */
    private function revenueByPressing(Carbon $start, Carbon $end): Collection
    {
        return OrderPayment::query()
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->whereIn('orders.pressing_id', $this->ids)
            ->where('orders.status', '!=', OrderStatus::Annulee->value)
            ->whereNull('orders.deleted_at')
            ->whereBetween('order_payments.paid_at', [$start, $end])
            ->selectRaw('orders.pressing_id as pressing_id, SUM(order_payments.amount_fcfa) as total')
            ->groupBy('orders.pressing_id')
            ->pluck('total', 'pressing_id');
    }

    /** @return array<int, int> */
    private function readyOverdueByPressing(): array
    {
        return Order::whereIn('pressing_id', $this->ids)
            ->where('status', OrderStatus::Prete->value)
            ->withReadySince()
            ->get(['id', 'pressing_id', 'status', 'updated_at'])
            ->filter(fn (Order $order) => $order->delayAlert() !== null)
            ->countBy('pressing_id')
            ->all();
    }

    /**
     * Délai moyen entre le dépôt et le passage à « prête », en heures, pour
     * les commandes devenues prêtes sur la période.
     *
     * @return array<int, int>
     */
    private function turnaroundHoursByPressing(): array
    {
        return OrderStatusHistory::query()
            ->join('orders', 'orders.id', '=', 'order_status_histories.order_id')
            ->whereIn('orders.pressing_id', $this->ids)
            ->where('order_status_histories.status', OrderStatus::Prete->value)
            ->whereBetween('order_status_histories.created_at', [$this->start, $this->end])
            ->get(['orders.pressing_id as pressing_id', 'orders.dropped_off_at as dropped_off_at', 'order_status_histories.created_at as ready_at'])
            ->groupBy('pressing_id')
            ->map(fn (Collection $rows) => (int) round($rows->avg(
                fn ($r) => max(0, Carbon::parse($r->dropped_off_at)->diffInMinutes(Carbon::parse($r->ready_at))) / 60
            )))
            ->all();
    }
}
