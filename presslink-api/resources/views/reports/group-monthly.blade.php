@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $colors = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];
    $maxRevenue = max(1, $rows->max('revenue'));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport du groupe · {{ ucfirst($month->translatedFormat('F Y')) }}</title>
    <style>
        @page { margin: 16mm 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5px; color: #0f172a; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 13px; margin: 22px 0 8px; }
        .muted { color: #64748b; }
        .band { background: #1e40af; color: #fff; padding: 14px 16px; border-radius: 6px; }
        .band .muted { color: #bfdbfe; }
        table { width: 100%; border-collapse: collapse; }
        .kpis td { width: 25%; padding: 10px; border: 1px solid #e2e8f0; vertical-align: top; }
        .kpis .v { font-size: 16px; font-weight: bold; margin-top: 3px; }
        .grid th { background: #f1f5f9; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .4px; color: #475569; padding: 6px; border-bottom: 1px solid #cbd5e1; }
        .grid td { padding: 6px; border-bottom: 1px solid #e2e8f0; }
        .num { text-align: right; white-space: nowrap; }
        .bar { height: 8px; border-radius: 3px; }
        .total td { font-weight: bold; border-top: 2px solid #0f172a; }
        .dot { display: inline-block; width: 8px; height: 8px; border-radius: 4px; margin-right: 5px; }
        .alert { padding: 6px 8px; border-left: 3px solid #b45309; background: #fffbeb; margin-bottom: 4px; }
        .alert.error { border-left-color: #dc2626; background: #fef2f2; }
        .footer { margin-top: 24px; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="band">
        <h1>Rapport du groupe · {{ ucfirst($month->translatedFormat('F Y')) }}</h1>
        <div class="muted">{{ $rows->count() }} pressings · du {{ $start->format('d/m/Y') }} au {{ $end->format('d/m/Y') }} · préparé pour {{ $user->name }}</div>
    </div>

    <h2>Chiffres clés</h2>
    <table class="kpis">
        <tr>
            <td><div class="muted">Encaissé</div><div class="v">{{ $fcfa($totals['revenue']) }} F</div></td>
            <td><div class="muted">Commandes déposées</div><div class="v">{{ $fcfa($totals['orders']) }}</div></td>
            <td><div class="muted">Panier moyen</div><div class="v">{{ $fcfa($totals['average_basket']) }} F</div></td>
            <td><div class="muted">Reste à encaisser</div><div class="v">{{ $fcfa($totals['outstanding']) }} F</div></td>
        </tr>
    </table>

    <h2>Comparatif des pressings</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Pressing</th><th class="num">Commandes</th><th class="num">Encaissé</th><th style="width:22%"></th>
                <th class="num">Panier moyen</th><th class="num">Délai moyen</th><th class="num">Reste dû</th><th class="num">Retards</th><th class="num">Signalements</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows->sortByDesc('revenue')->values() as $i => $row)
                @php $color = $colors[$rows->search(fn ($r) => $r['pressing']->id === $row['pressing']->id) % 8]; @endphp
                <tr>
                    <td><span class="dot" style="background:{{ $color }}"></span>{{ $row['pressing']->name }}</td>
                    <td class="num">{{ $row['orders'] }}</td>
                    <td class="num">{{ $fcfa($row['revenue']) }} F</td>
                    <td><div class="bar" style="width:{{ max(2, round($row['revenue'] / $maxRevenue * 100)) }}%;background:{{ $color }}"></div></td>
                    <td class="num">{{ $fcfa($row['average_basket']) }} F</td>
                    <td class="num">{{ $row['turnaround_hours'] === null ? '—' : ($row['turnaround_hours'] < 48 ? $row['turnaround_hours'].' h' : round($row['turnaround_hours'] / 24, 1).' j') }}</td>
                    <td class="num">{{ $fcfa($row['outstanding']) }} F</td>
                    <td class="num">{{ $row['late'] }}</td>
                    <td class="num">{{ $row['open_issues'] }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Groupe</td><td class="num">{{ $totals['orders'] }}</td><td class="num">{{ $fcfa($totals['revenue']) }} F</td><td></td>
                <td class="num">{{ $fcfa($totals['average_basket']) }} F</td><td></td><td class="num">{{ $fcfa($totals['outstanding']) }} F</td>
                <td class="num">{{ $totals['late'] }}</td><td class="num">{{ $totals['open_issues'] }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Encaissé par semaine</h2>
    @php
        $weeks = [];
        foreach ($chart['days'] as $i => $day) {
            $key = \Illuminate\Support\Carbon::parse($day)->startOfWeek()->format('d/m');
            foreach ($chart['series'] as $s) {
                $weeks[$key][$s['pressing']->name] = ($weeks[$key][$s['pressing']->name] ?? 0) + $s['values'][$i];
            }
        }
    @endphp
    <table class="grid">
        <thead><tr><th>Semaine du</th>@foreach ($chart['series'] as $k => $s)<th class="num"><span class="dot" style="background:{{ $colors[$k % 8] }}"></span>{{ $s['pressing']->name }}</th>@endforeach<th class="num">Total</th></tr></thead>
        <tbody>
            @foreach ($weeks as $label => $values)
                <tr><td>{{ $label }}</td>@foreach ($chart['series'] as $s)<td class="num">{{ $fcfa($values[$s['pressing']->name] ?? 0) }} F</td>@endforeach<td class="num"><b>{{ $fcfa(array_sum($values)) }} F</b></td></tr>
            @endforeach
        </tbody>
    </table>

    @if ($alerts !== [])
        <h2>Points d'attention (à la date du rapport)</h2>
        @foreach ($alerts as $alert)
            <div class="alert {{ $alert['tone'] === 'error' ? 'error' : '' }}"><b>{{ $alert['pressing']->name }}</b> — {{ $alert['title'] }}. <span class="muted">{{ $alert['hint'] }}</span></div>
        @endforeach
    @endif

    <div class="footer">PressLink · rapport généré le {{ now()->format('d/m/Y à H:i') }} · presslink.org</div>
</body>
</html>
