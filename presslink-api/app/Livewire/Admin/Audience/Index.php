<?php

namespace App\Livewire\Admin\Audience;

use App\Models\AudienceEvent;
use App\Models\Pressing;
use App\Models\SandboxTrial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Audience du site vitrine : visiteurs, visites, pages vues, clics, sites
 * d'origine, appareils, et le chemin jusqu'à l'inscription. Données
 * mesurées par PressLink lui-même (AudienceEvent), sans cookie.
 */
class Index extends Component
{
    /** Visiteurs « en ce moment » : actifs dans les dernières minutes. */
    public const LIVE_MINUTES = 5;

    #[Url(as: 'periode', except: '30')]
    public string $period = '30';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        if (! in_array($this->period, ['today', '7', '30', '90'], true)) {
            $this->period = '30';
        }
    }

    private function periodStart(): Carbon
    {
        return match ($this->period) {
            'today' => now()->startOfDay(),
            '7' => now()->subDays(6)->startOfDay(),
            '90' => now()->subDays(89)->startOfDay(),
            default => now()->subDays(29)->startOfDay(),
        };
    }

    /** @return Builder<AudienceEvent> */
    private function between(Carbon $from, Carbon $to): Builder
    {
        return AudienceEvent::query()->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to);
    }

    /**
     * Visiteurs (uniques par jour, additionnés), visites, pages vues et clics.
     *
     * @return array{visitors: int, visits: int, pageviews: int, clicks: int}
     */
    private function totals(Carbon $from, Carbon $to): array
    {
        $visitors = (int) $this->between($from, $to)->pages()
            ->selectRaw('COUNT(DISTINCT visitor) as visitors, DATE(occurred_at) as day')
            ->groupBy('day')
            ->get()
            ->sum('visitors');

        $pages = $this->between($from, $to)->pages()
            ->selectRaw('COUNT(*) as pageviews, SUM(CASE WHEN is_entry = 1 THEN 1 ELSE 0 END) as visits')
            ->first();

        return [
            'visitors' => $visitors,
            'visits' => (int) ($pages->visits ?? 0),
            'pageviews' => (int) ($pages->pageviews ?? 0),
            'clicks' => $this->between($from, $to)->clicks()->count(),
        ];
    }

    /**
     * Visiteurs par heure (aujourd'hui), par jour (jusqu'à 30 jours) ou par
     * semaine (90 jours).
     *
     * @return array{bars: list<array{label: string, count: int}>, max: int}
     */
    private function chart(Carbon $since): array
    {
        $bars = [];

        if ($this->period === 'today') {
            $perHour = $this->between($since, now()->addSecond())->pages()->get(['visitor', 'occurred_at'])
                ->groupBy(fn (AudienceEvent $event) => (int) $event->occurred_at->format('G'))
                ->map(fn ($events) => $events->pluck('visitor')->unique()->count());
            for ($hour = 0; $hour <= (int) now()->format('G'); $hour++) {
                $bars[] = ['label' => $hour.'h', 'count' => (int) ($perHour[$hour] ?? 0)];
            }
        } else {
            $perDay = $this->between($since, now()->addSecond())->pages()
                ->selectRaw('DATE(occurred_at) as day, COUNT(DISTINCT visitor) as visitors')
                ->groupBy('day')
                ->pluck('visitors', 'day');
            $weekly = $this->period === '90';
            $cursor = $weekly ? $since->copy()->startOfWeek() : $since->copy();
            $today = now()->startOfDay();

            while ($cursor->lte($today)) {
                $next = $cursor->copy()->addDays($weekly ? 7 : 1);
                $count = 0;
                for ($day = $cursor->copy(); $day->lt($next) && $day->lte($today); $day->addDay()) {
                    $count += (int) ($perDay[$day->toDateString()] ?? 0);
                }
                $bars[] = ['label' => $cursor->format('d/m'), 'count' => $count];
                $cursor = $next;
            }
        }

        return ['bars' => $bars, 'max' => max(1, ...array_column($bars, 'count'))];
    }

    /** Évolution en % par rapport à la période précédente de même durée. */
    private function change(int $current, int $previous): ?int
    {
        return $previous > 0 ? (int) round(($current - $previous) / $previous * 100) : null;
    }

    #[Layout('layouts.admin', ['active' => 'audience', 'title' => 'Audience du site'])]
    public function render()
    {
        $since = $this->periodStart();
        $until = now()->addSecond();
        $previousSince = $since->copy()->subSeconds((int) $since->diffInSeconds(now()));

        $totals = $this->totals($since, $until);
        $previous = $this->totals($previousSince, $since);
        $changes = [];
        foreach ($totals as $key => $value) {
            $changes[$key] = $this->change($value, $previous[$key]);
        }

        $period = fn () => $this->between($since, $until);

        $topPages = $period()->pages()
            ->selectRaw('path, COUNT(*) as views, COUNT(DISTINCT visitor) as visitors')
            ->groupBy('path')->orderByDesc('views')->limit(15)->get();

        $topClicks = $period()->clicks()
            ->selectRaw('label, target, COUNT(*) as clicks, COUNT(DISTINCT visitor) as visitors')
            ->groupBy('label', 'target')->orderByDesc('clicks')->limit(20)->get();

        $sources = $period()->pages()->where('is_entry', true)
            ->selectRaw('source, COUNT(*) as visits')
            ->groupBy('source')->orderByDesc('visits')->limit(10)->get();

        $devices = $period()->pages()->where('is_entry', true)
            ->selectRaw('device, COUNT(*) as visits')
            ->groupBy('device')->orderByDesc('visits')->pluck('visits', 'device');

        $pageViews = fn (string $path) => $period()->pages()->where('path', $path)->distinct()->count('visitor');
        $funnel = [
            'signup' => [
                'views' => $pageViews(parse_url(route('signup'), PHP_URL_PATH)),
                'done' => Pressing::withoutDemo()->withoutSandboxes()->where('created_at', '>=', $since)->count(),
            ],
            'sandbox' => [
                'views' => $pageViews(parse_url(route('sandbox.start'), PHP_URL_PATH)),
                'done' => SandboxTrial::where('created_at', '>=', $since)->count(),
            ],
        ];

        return view('livewire.admin.audience.index', [
            'totals' => $totals,
            'changes' => $changes,
            'chart' => $this->chart($since),
            'topPages' => $topPages,
            'topClicks' => $topClicks,
            'sources' => $sources,
            'devices' => $devices,
            'funnel' => $funnel,
            'liveVisitors' => AudienceEvent::where('occurred_at', '>=', now()->subMinutes(self::LIVE_MINUTES))->distinct()->count('visitor'),
            'hasData' => AudienceEvent::query()->exists(),
        ]);
    }
}
