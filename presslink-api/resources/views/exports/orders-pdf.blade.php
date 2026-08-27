<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Commandes — {{ $pressing->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .subtitle { color: #475569; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: 5px 7px; text-align: left; }
        th { background: #f8fafc; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.4px; color: #475569; }
        td.num { text-align: right; }
        tfoot td { font-weight: bold; border-top: 2px solid #0f172a; }
    </style>
</head>
<body>
    <h1>Commandes — {{ $pressing->name }}</h1>
    <p class="subtitle">
        Exporté le {{ now()->format('d/m/Y à H:i') }}
        @if ($filtersSummary)
            — Filtres : {{ $filtersSummary }}
        @endif
        — {{ $orders->count() }} commande(s)
    </p>

    <table>
        <thead>
            <tr>
                <th>N° commande</th>
                <th>Client</th>
                <th>Téléphone</th>
                <th>Statut</th>
                <th>Total</th>
                <th>Déposée le</th>
                <th>Retrait prévu</th>
                <th>Récupérée le</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orders as $order)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ $order->customer->fullName() }}</td>
                    <td>{{ $order->customer->phone }}</td>
                    <td>{{ $order->status->label() }}</td>
                    <td class="num">{{ number_format($order->total_fcfa, 0, ',', ' ') }} F</td>
                    <td>{{ $order->dropped_off_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $order->expected_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $order->recovered_at?->format('d/m/Y') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Total</td>
                <td class="num">{{ number_format($orders->sum('total_fcfa'), 0, ',', ' ') }} F</td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
