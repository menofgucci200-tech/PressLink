@php
    $number = fn (int $n) => number_format($n, 0, ',', ' ');
    $periods = ['today' => "Aujourd'hui", '7' => '7 jours', '30' => '30 jours', '90' => '90 jours'];
    $periodLabel = match ($period) {
        'today' => "aujourd'hui",
        '7' => 'sur 7 jours',
        '90' => 'sur 90 jours',
        default => 'sur 30 jours',
    };
    $change = fn (?int $pct) => $pct === null ? '' : '<span class="font-semibold '.($pct >= 0 ? 'text-(--color-success-text)' : 'text-(--color-error)').'" title="Par rapport à la période précédente de même durée">'.($pct >= 0 ? '+' : '').$pct.' %</span> · ';
    // Nom lisible des pages du site, l'adresse reste affichée en dessous.
    $pageNames = [
        '/' => 'Accueil',
        '/pour-les-clients' => 'Pour les clients',
        '/pour-les-pressings' => 'Pour les pressings',
        '/offres' => 'Nos offres',
        '/comment-ca-marche' => 'Comment ça marche',
        '/installer' => 'Installer l\'application',
        '/questions-frequentes' => 'Questions fréquentes',
        '/guides' => 'Guides',
        '/contact' => 'Contact',
        '/mentions-legales' => 'Mentions légales',
        '/conditions-generales' => 'Conditions générales',
        '/confidentialite' => 'Confidentialité',
        '/inscription' => 'Inscription d\'un pressing',
        '/tester' => 'Tester avec un pressing fictif',
    ];
    $pageName = fn (string $path) => $pageNames[$path]
        ?? (isset(\App\Support\Guides::ALL[substr($path, 8)]) && str_starts_with($path, '/guides/') ? 'Guide : '.\App\Support\Guides::ALL[substr($path, 8)]['title'] : $path);
    $plural = fn (int $n, string $one, string $many) => $number($n).' '.($n > 1 ? $many : $one);
    $kpis = [
        ['key' => 'visitors', 'label' => 'Visiteurs', 'sub' => 'personnes différentes, comptées chaque jour'],
        ['key' => 'visits', 'label' => 'Visites', 'sub' => 'arrivées sur le site'],
        ['key' => 'pageviews', 'label' => 'Pages vues', 'sub' => $totals['visits'] > 0 ? number_format($totals['pageviews'] / $totals['visits'], 1, ',', ' ').' pages par visite' : 'pages ouvertes'],
        ['key' => 'clicks', 'label' => 'Clics', 'sub' => 'sur les liens et les boutons'],
    ];
    $deviceTotal = max(1, (int) $devices->sum());
    $sourceTotal = max(1, (int) $sources->sum('visits'));
    $rate = fn (int $done, int $views) => $views > 0 ? number_format(min(100, $done / $views * 100), 1, ',', ' ').' %' : '—';

    $card = 'rounded-xl border border-(--color-border) bg-(--color-surface) p-4 shadow-sm';
    $cardTitle = 'mb-3 text-sm font-medium text-(--color-text-muted)';
    $bar = 'h-1.5 rounded-full bg-(--color-primary)';
@endphp

<div class="space-y-6">
    {{-- ===== Titre, période ===== --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Audience du site</h1>
            <p class="text-sm text-(--color-text-muted)">
                Pages publiques, inscription et essai · mesurée par PressLink, sans cookie
                · <span class="inline-flex items-center gap-1.5 font-medium text-(--color-text-primary)" title="Visiteurs actifs dans les {{ \App\Livewire\Admin\Audience\Index::LIVE_MINUTES }} dernières minutes">
                    <span class="w-2 h-2 rounded-full {{ $liveVisitors > 0 ? 'bg-(--color-success) animate-pulse' : 'bg-(--color-border)' }}"></span>
                    {{ $liveVisitors }} en ce moment
                </span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span wire:loading.delay class="text-xs text-(--color-text-muted)">Mise à jour…</span>
            <div class="inline-flex max-w-full overflow-x-auto rounded-lg border border-(--color-border) bg-(--color-surface) p-0.5" role="group" aria-label="Période">
                @foreach ($periods as $value => $label)
                    <button type="button" wire:click="$set('period', '{{ $value }}')" aria-pressed="{{ $period === (string) $value ? 'true' : 'false' }}"
                            @class(['rounded-md px-3 py-1 text-sm whitespace-nowrap', 'bg-(--color-primary) text-white' => $period === (string) $value, 'hover:bg-(--color-bg)' => $period !== (string) $value])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    @unless ($hasData)
        <div class="{{ $card }} text-sm text-(--color-text-secondary)">
            Aucune visite enregistrée pour l'instant. Les chiffres apparaîtront ici dès les premières visites du site.
        </div>
    @endunless

    <div class="space-y-6 loading-dim rounded-xl" wire:loading.delay.class="is-loading" wire:target="period">
        {{-- ===== Chiffres clés ===== --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpis as $kpi)
                <div wire:key="kpi-{{ $kpi['key'] }}-{{ $period }}" class="{{ $card }}" data-testid="kpi-{{ $kpi['key'] }}">
                    <p class="text-sm text-(--color-text-muted)">{{ $kpi['label'] }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $number($totals[$kpi['key']]) }}</p>
                    <p class="text-xs text-(--color-text-muted)">{!! $change($changes[$kpi['key']]) !!}{{ $kpi['sub'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- ===== Visiteurs dans le temps ===== --}}
        <div class="{{ $card }}">
            <h2 class="{{ $cardTitle }}">Visiteurs {{ $period === 'today' ? 'par heure' : ($period === '90' ? 'par semaine' : 'par jour') }} ({{ $periodLabel }})</h2>
            @php
                $bars = $chart['bars'];
                $labelEvery = max(1, (int) ceil(count($bars) / 12));
                $rawStep = max(1, $chart['max'] / 4);
                $magnitude = 10 ** floor(log10($rawStep));
                $step = collect([1, 2, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($s) => $s >= $rawStep);
                $axisTop = $step * 4;
            @endphp
            @if ($totals['visitors'] === 0)
                <div class="min-h-[180px] flex items-center justify-center text-sm text-(--color-text-muted)">Aucune visite sur cette période.</div>
            @else
                <div wire:key="chart-{{ $period }}" class="relative h-48 pl-10">
                    @for ($i = 0; $i <= 4; $i++)
                        <div class="absolute left-10 right-0 border-t border-dashed border-(--color-border)" style="bottom:{{ $i * 25 }}%"></div>
                        <div class="absolute left-0 w-8 text-right text-[11px] text-(--color-text-muted) tabular-nums translate-y-1/2" style="bottom:{{ $i * 25 }}%">{{ $number((int) ($step * $i)) }}</div>
                    @endfor
                    <div class="absolute inset-y-0 left-10 right-0 flex items-end gap-[3px] sm:gap-1">
                        @foreach ($bars as $item)
                            <div class="group relative flex-1 h-full flex items-end" role="img" aria-label="{{ $item['label'] }} : {{ $item['count'] }} visiteur{{ $item['count'] > 1 ? 's' : '' }}">
                                <div class="w-full rounded-t-[3px] bg-(--color-primary) group-hover:bg-(--color-primary-600)" style="height:{{ $item['count'] > 0 ? max(1, round($item['count'] / $axisTop * 100, 1)) : 0 }}%"></div>
                                <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:block whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[11px] font-medium text-white z-10">
                                    {{ $item['label'] }} · {{ $item['count'] }} visiteur{{ $item['count'] > 1 ? 's' : '' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="flex gap-[3px] sm:gap-1 mt-2 pl-10 text-[11px] text-(--color-text-muted) tabular-nums">
                    @foreach ($bars as $item)
                        <div class="flex-1 min-w-0 text-center overflow-visible whitespace-nowrap">{{ $loop->index % $labelEvery === 0 ? $item['label'] : '' }}</div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ===== Du site à l'inscription ===== --}}
        <div class="{{ $card }}">
            <h2 class="{{ $cardTitle }}">Du site à l'inscription ({{ $periodLabel }})</h2>
            <div class="grid gap-4 md:grid-cols-2 text-sm">
                @foreach ([
                    ['key' => 'signup', 'title' => 'Créer un compte pressing', 'page' => 'la page d\'inscription', 'done' => ['pressing inscrit', 'pressings inscrits'], 'end' => 'se sont inscrits'],
                    ['key' => 'sandbox', 'title' => 'Tester avec un pressing fictif', 'page' => 'la page de test', 'done' => ['test lancé', 'tests lancés'], 'end' => 'ont lancé un test'],
                ] as $step)
                    <div class="rounded-lg bg-(--color-bg) p-3" data-testid="funnel-{{ $step['key'] }}">
                        <p class="font-semibold mb-2">{{ $step['title'] }}</p>
                        <div class="flex items-center gap-2 flex-wrap tabular-nums">
                            <span>{{ $plural($totals['visitors'], 'visiteur', 'visiteurs') }}</span>
                            <span class="text-(--color-text-muted)" aria-hidden="true">›</span>
                            <span>{{ $plural($funnel[$step['key']]['views'], 'a ouvert', 'ont ouvert') }} {{ $step['page'] }}</span>
                            <span class="text-(--color-text-muted)" aria-hidden="true">›</span>
                            <span class="font-semibold">{{ $plural($funnel[$step['key']]['done'], ...$step['done']) }}</span>
                        </div>
                        <p class="mt-1.5 text-xs text-(--color-text-muted)">Sur les visiteurs de {{ $step['page'] }}, {{ $rate($funnel[$step['key']]['done'], $funnel[$step['key']]['views']) }} {{ $step['end'] }}{{ $step['key'] === 'signup' ? ' (hors tests, créations par l\'équipe comprises)' : '' }}.</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            {{-- ===== Pages les plus vues ===== --}}
            <div class="{{ $card }} xl:col-span-2">
                <h2 class="{{ $cardTitle }}">Pages les plus vues</h2>
                @if ($topPages->isEmpty())
                    <p class="py-6 text-center text-sm text-(--color-text-muted)">Aucune page vue sur cette période.</p>
                @else
                    <div class="overflow-x-auto"><table class="w-full sm:min-w-[420px] text-sm">
                        <thead>
                            <tr class="text-left text-(--color-text-muted)">
                                <th class="py-1 pr-4 font-semibold">Page</th>
                                <th class="py-1 pr-4 font-semibold text-right">Vues</th>
                                <th class="py-1 font-semibold text-right">Visiteurs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--color-border)">
                            @foreach ($topPages as $row)
                                <tr wire:key="page-{{ md5($row->path) }}">
                                    <td class="py-2 pr-4">
                                        <span class="block font-medium">{{ $pageName($row->path) }}</span>
                                        <span class="block text-xs text-(--color-text-muted) break-all">{{ $row->path }}</span>
                                    </td>
                                    <td class="py-2 pr-4 text-right tabular-nums font-semibold">{{ $number((int) $row->views) }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $number((int) $row->visitors) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>

            <div class="space-y-4">
                {{-- ===== D'où viennent les visiteurs ===== --}}
                <div class="{{ $card }}">
                    <h2 class="{{ $cardTitle }}">D'où viennent les visites</h2>
                    @if ($sources->isEmpty())
                        <p class="py-4 text-center text-sm text-(--color-text-muted)">Aucune visite sur cette période.</p>
                    @else
                        <ul class="space-y-2.5 text-sm">
                            @foreach ($sources as $row)
                                <li wire:key="source-{{ md5((string) $row->source) }}">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="min-w-0 truncate" @if ($row->source === null) title="Adresse tapée, favori, application installée ou lien partagé sans origine (souvent WhatsApp)" @endif>{{ $row->source !== null ? ucfirst($row->source) : 'Accès direct ou lien partagé' }}</span>
                                        <span class="shrink-0 tabular-nums font-semibold">{{ $number((int) $row->visits) }}</span>
                                    </div>
                                    <div class="mt-1 h-1.5 rounded-full bg-(--color-bg)"><div class="{{ $bar }}" style="width:{{ round($row->visits / $sourceTotal * 100, 1) }}%"></div></div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- ===== Appareils ===== --}}
                <div class="{{ $card }}">
                    <h2 class="{{ $cardTitle }}">Appareils</h2>
                    @if ($devices->isEmpty())
                        <p class="py-4 text-center text-sm text-(--color-text-muted)">Aucune visite sur cette période.</p>
                    @else
                        <ul class="space-y-2.5 text-sm">
                            @foreach ($devices as $device => $visits)
                                <li wire:key="device-{{ $device }}">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span>{{ ucfirst($device) }}</span>
                                        <span class="tabular-nums"><span class="font-semibold">{{ round($visits / $deviceTotal * 100) }} %</span> <span class="text-xs text-(--color-text-muted)">({{ $number((int) $visits) }})</span></span>
                                    </div>
                                    <div class="mt-1 h-1.5 rounded-full bg-(--color-bg)"><div class="{{ $bar }}" style="width:{{ round($visits / $deviceTotal * 100, 1) }}%"></div></div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== Clics ===== --}}
        <div class="{{ $card }}">
            <h2 class="{{ $cardTitle }}">Clics les plus fréquents</h2>
            @if ($topClicks->isEmpty())
                <p class="py-6 text-center text-sm text-(--color-text-muted)">Aucun clic sur cette période.</p>
            @else
                <div class="overflow-x-auto"><table class="w-full min-w-[560px] text-sm">
                    <thead>
                        <tr class="text-left text-(--color-text-muted)">
                            <th class="py-1 pr-4 font-semibold">Lien ou bouton</th>
                            <th class="py-1 pr-4 font-semibold">Vers</th>
                            <th class="py-1 pr-4 font-semibold text-right">Clics</th>
                            <th class="py-1 font-semibold text-right">Visiteurs</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-(--color-border)">
                        @foreach ($topClicks as $row)
                            <tr wire:key="click-{{ md5($row->label.'|'.$row->target) }}">
                                <td class="py-2 pr-4 font-medium">{{ $row->label ?? 'Sans texte' }}</td>
                                <td class="py-2 pr-4 text-xs text-(--color-text-muted) break-all">{{ $row->target ? $pageName($row->target) : '—' }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums font-semibold">{{ $number((int) $row->clicks) }}</td>
                                <td class="py-2 text-right tabular-nums">{{ $number((int) $row->visitors) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
    </div>

    {{-- Vos propres visites faussent les chiffres : on peut exclure cet appareil (mémorisé dans le navigateur). --}}
    <div class="text-xs text-(--color-text-muted)" x-data="{
            ignored: (() => { try { return localStorage.getItem('presslink-audience-ignore') === '1' } catch { return false } })(),
            toggle() { this.ignored = ! this.ignored; try { this.ignored ? localStorage.setItem('presslink-audience-ignore', '1') : localStorage.removeItem('presslink-audience-ignore') } catch {} },
         }">
        <span x-text="ignored ? 'Vos visites sur cet appareil ne sont pas comptées.' : 'Vos visites du site sur cet appareil sont comptées.'"></span>
        <button type="button" @click="toggle()" class="ml-1 font-medium text-(--color-primary) underline underline-offset-2 hover:no-underline" x-text="ignored ? 'Les compter à nouveau' : 'Ne plus compter cet appareil'" data-testid="audience-ignore"></button>
        <span class="block mt-1">Robots et aperçus de liens ne sont jamais comptés. Les données de plus de 12 mois sont supprimées.</span>
    </div>
</div>
