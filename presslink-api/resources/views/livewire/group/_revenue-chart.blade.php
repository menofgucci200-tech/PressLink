{{--
    Évolution de l'encaissé par jour, une courbe par pressing (couleur fixe
    par pressing dans l'ordre du groupe). Survol : ligne verticale et
    valeurs du jour. Le tableau comparatif sert de vue tableau.
--}}
@php
    $w = 640; $h = 220; $padL = 8; $padR = 8; $padT = 14; $padB = 26;
    $n = count($chart['days']);
    $x = fn (int $i) => $n > 1 ? $padL + $i * (($w - $padL - $padR) / ($n - 1)) : $w / 2;
    $y = fn (int $v) => $padT + ($h - $padT - $padB) * (1 - $v / $chart['max']);
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $labelEvery = max(1, (int) ceil($n / 8));
    $series = collect($chart['series'])->values();
    $directLabels = $series->count() <= 4;
    $tooltipData = collect($chart['days'])->map(fn ($day, $i) => [
        'label' => \Illuminate\Support\Carbon::parse($day)->translatedFormat('D j M'),
        'values' => $series->map(fn ($s, $k) => ['name' => $s['pressing']->name, 'slot' => $k % 8 + 1, 'value' => $fcfa($s['values'][$i])])->all(),
    ])->all();
@endphp
<div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 viz-root"
     x-data="{ i: null, data: @js($tooltipData), n: {{ $n }} }">
    <div class="flex items-baseline justify-between gap-3 mb-3">
        <h2 class="font-semibold text-sm">Encaissé par jour</h2>
        <span class="text-xs text-(--color-text-muted)">{{ $chart['labels'][0] ?? '' }} → {{ $chart['labels'][$n - 1] ?? '' }}</span>
    </div>

    {{-- Légende (toujours présente dès 2 pressings) --}}
    <div class="flex flex-wrap gap-x-4 gap-y-1.5 mb-3 text-xs text-(--color-text-secondary)">
        @foreach ($series as $k => $s)
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-[3px] rounded-full" style="background:var(--series-{{ $k % 8 + 1 }})"></span>{{ $s['pressing']->name }}</span>
        @endforeach
    </div>

    <div class="relative" @mouseleave="i = null"
         @mousemove="const r = $el.getBoundingClientRect(); const px = ($event.clientX - r.left) / r.width * {{ $w }}; i = n > 1 ? Math.max(0, Math.min(n - 1, Math.round((px - {{ $padL }}) / (({{ $w - $padL - $padR }}) / (n - 1))))) : 0">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="w-full h-48 md:h-auto md:aspect-[640/220] block" role="img" aria-label="Encaissé par jour et par pressing">
            @foreach ([0, 0.5, 1] as $g)
                <line x1="{{ $padL }}" x2="{{ $w - $padR }}" y1="{{ $padT + ($h - $padT - $padB) * $g }}" y2="{{ $padT + ($h - $padT - $padB) * $g }}" stroke="var(--color-border)" stroke-width="1" vector-effect="non-scaling-stroke" />
            @endforeach

            @foreach ($series as $k => $s)
                @php $points = collect($s['values'])->map(fn ($v, $i) => round($x($i), 1).','.round($y($v), 1))->implode(' '); @endphp
                <polyline points="{{ $points }}" fill="none" stroke="var(--series-{{ $k % 8 + 1 }})" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
            @endforeach

            {{-- Repère vertical au survol --}}
            <line x-show="i !== null" :x1="n > 1 ? {{ $padL }} + (i ?? 0) * ({{ $w - $padL - $padR }} / (n - 1)) : {{ $w / 2 }}" :x2="n > 1 ? {{ $padL }} + (i ?? 0) * ({{ $w - $padL - $padR }} / (n - 1)) : {{ $w / 2 }}"
                  y1="{{ $padT }}" y2="{{ $h - $padB }}" stroke="var(--color-text-muted)" stroke-width="1" stroke-dasharray="3 3" vector-effect="non-scaling-stroke" />
        </svg>

        {{-- Textes en HTML : ils gardent leur taille quelle que soit la largeur du graphique. --}}
        <span class="absolute left-0 -translate-y-full text-[10.5px] text-(--color-text-muted) pointer-events-none" style="top:{{ round(($padT - 2) / $h * 100, 2) }}%">{{ $fcfa($chart['max']) }} F</span>
        @foreach ($chart['labels'] as $i => $label)
            @if ($i % $labelEvery === 0 || $i === $n - 1)
                <span class="absolute bottom-0 text-[10.5px] text-(--color-text-muted) pointer-events-none {{ $i === 0 ? '' : ($i === $n - 1 ? '-translate-x-full' : '-translate-x-1/2') }} {{ $i !== 0 && $i !== $n - 1 && $i % ($labelEvery * 2) !== 0 ? 'max-md:hidden' : '' }}"
                      style="left:{{ round($x($i) / $w * 100, 2) }}%">{{ $label }}</span>
            @endif
        @endforeach

        @if ($directLabels)
            {{-- Étiquettes directes en fin de courbe (≤ 4 pressings) --}}
            @foreach ($series as $k => $s)
                @php $last = end($s['values']); @endphp
                <span class="absolute right-0 -translate-y-1/2 text-[10.5px] font-semibold text-(--color-text-secondary) bg-(--color-surface)/80 px-1 rounded pointer-events-none"
                      style="top:{{ round($y((int) $last) / $h * 100, 2) }}%">{{ $fcfa((int) $last) }} F</span>
            @endforeach
        @endif

        <div x-show="i !== null" x-cloak
             class="absolute top-2 pointer-events-none z-10 w-max max-w-[min(20rem,100%)] whitespace-nowrap rounded-lg bg-(--color-surface) border border-(--color-border) shadow-lg px-3 py-2 text-xs"
             :style="(n > 1 ? i / (n - 1) : 0.5) > 0.55 ? `right: calc(${100 - (n > 1 ? i / (n - 1) * 100 : 50)}% + 12px)` : `left: calc(${n > 1 ? i / (n - 1) * 100 : 50}% + 12px)`">
            <div class="font-semibold mb-1" x-text="data[i]?.label"></div>
            <template x-for="row in (data[i]?.values ?? [])" :key="row.name">
                <div class="flex items-center justify-between gap-4 py-0.5">
                    <span class="inline-flex items-center gap-1.5 text-(--color-text-secondary)"><span class="w-2 h-2 rounded-full" :style="`background:var(--series-${row.slot})`"></span><span x-text="row.name"></span></span>
                    <span class="font-semibold tabular-nums" x-text="row.value + ' F'"></span>
                </div>
            </template>
        </div>
    </div>
</div>
