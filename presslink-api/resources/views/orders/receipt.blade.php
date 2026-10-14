@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $paymentState = $order->paymentStatus();
    $balance = $order->balance_due_fcfa;
    $logo = null;
    if ($pressing->logo_path) {
        // dompdf lit les images sur le disque ; le navigateur, par l'URL.
        $logo = $isPdf
            ? (\Illuminate\Support\Facades\Storage::disk('public')->exists($pressing->logo_path) ? \Illuminate\Support\Facades\Storage::disk('public')->path($pressing->logo_path) : null)
            : $pressing->logo_url;
    }
    $stateColors = match ($paymentState) {
        \App\Enums\PaymentStatus::Paid => ['#047857', '#ecfdf5'],
        \App\Enums\PaymentStatus::Partial => ['#b45309', '#fffbeb'],
        \App\Enums\PaymentStatus::Unpaid => ['#334155', '#f1f5f9'],
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reçu {{ $order->order_number }} · {{ $pressing->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: {!! $isPdf ? '"DejaVu Sans", sans-serif' : 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif' !!};
            color: #0f172a;
            background: {{ $isPdf ? '#ffffff' : '#e2e8f0' }};
            font-size: {{ $size === 'ticket' ? '12px' : '12.5px' }};
            line-height: 1.4;
        }
        .toolbar {
            position: sticky; top: 0; z-index: 2;
            display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: center;
            padding: 12px 16px; background: #0f172a; color: #fff;
        }
        .toolbar a, .toolbar button {
            display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px;
            border-radius: 8px; border: 1px solid #334155; background: #1e293b; color: #fff;
            font: 600 13px system-ui, sans-serif; text-decoration: none; cursor: pointer;
        }
        .toolbar .primary { background: #2563eb; border-color: #2563eb; }
        .toolbar .on { background: #fff; color: #0f172a; border-color: #fff; }
        .toolbar .sep { width: 1px; height: 24px; background: #334155; margin: 0 4px; }
        .sheet {
            @if ($isPdf)
                {{-- dompdf : la page A5 et ses marges suffisent (pas de box-sizing). --}}
                width: auto; margin: 0; padding: 0;
            @else
                width: {{ $size === 'ticket' ? '80mm' : '148mm' }};
                max-width: 100%;
                margin: 24px auto;
                padding: {{ $size === 'ticket' ? '6mm 5mm' : '10mm 11mm' }};
                box-shadow: 0 10px 30px -12px rgba(15, 23, 42, .35); border-radius: 4px;
            @endif
            background: #fff;
        }
        .center { text-align: center; }
        .muted { color: #64748b; }
        .small { font-size: 0.85em; }
        .bold { font-weight: bold; }
        .logo { max-height: 42px; max-width: 120px; margin-bottom: 6px; }
        .pressing-name { font-size: 1.35em; font-weight: bold; margin: 0; }
        .title {
            margin: 10px 0 2px; font-size: 0.8em; letter-spacing: 2px; text-transform: uppercase; color: #64748b;
        }
        .number { font-size: 1.5em; font-weight: bold; letter-spacing: 0.5px; margin: 0; }
        .rule { border: 0; border-top: 1px dashed #94a3b8; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        td.r { text-align: right; white-space: nowrap; }
        .items td { padding: 4px 0; }
        .items .qty { width: 1%; color: #64748b; white-space: nowrap; padding-right: 8px; }
        .total td { font-size: 1.2em; font-weight: bold; padding-top: 6px; }
        .state {
            margin: 10px 0 6px; padding: 6px 8px; text-align: center; font-weight: bold; letter-spacing: 1px;
            text-transform: uppercase; font-size: 0.9em; border-radius: 4px;
            color: {{ $stateColors[0] }}; background: {{ $stateColors[1] }}; border: 1px solid {{ $stateColors[0] }};
        }
        .balance td { font-size: 1.15em; font-weight: bold; padding-top: 4px; }
        .note { margin-top: 8px; padding: 6px 8px; background: #f8fafc; border-left: 3px solid #cbd5e1; }
        .footer { margin-top: 12px; text-align: center; color: #64748b; font-size: 0.85em; }
        .code { font-family: "DejaVu Sans Mono", ui-monospace, monospace; font-weight: bold; color: #0f172a; letter-spacing: 1px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0 auto; box-shadow: none; border-radius: 0; }
        }
        @page { size: {{ $size === 'ticket' ? '80mm auto' : 'A5' }}; margin: {{ $isPdf ? '12mm 13mm' : ($size === 'ticket' ? '0' : '8mm') }}; }
    </style>
</head>
<body>
    @unless ($isPdf)
        <div class="toolbar">
            <button type="button" class="primary" onclick="window.print()">Imprimer</button>
            <a href="{{ route('orders.receipt', ['order' => $order, 'format' => 'pdf']) }}">Télécharger le PDF</a>
            <span class="sep"></span>
            <a href="{{ route('orders.receipt', ['order' => $order]) }}" class="{{ $size === 'ticket' ? 'on' : '' }}">Ticket 80 mm</a>
            <a href="{{ route('orders.receipt', ['order' => $order, 'taille' => 'a5']) }}" class="{{ $size === 'a5' ? 'on' : '' }}">Feuille A5</a>
        </div>
    @endunless

    <div class="sheet">
        <div class="center">
            @if ($logo)
                <img src="{{ $logo }}" alt="" class="logo">
            @endif
            <p class="pressing-name">{{ $pressing->name }}</p>
            @if ($pressing->address || $pressing->city)
                <div class="muted small">{{ collect([$pressing->address, $pressing->city])->filter()->implode(', ') }}</div>
            @endif
            @if ($pressing->phone)
                <div class="muted small">Tél. {{ $pressing->phone }}</div>
            @endif
            <p class="title">Reçu de dépôt</p>
            <p class="number">{{ $order->order_number }}</p>
        </div>

        <hr class="rule">

        <table>
            <tr><td class="muted">Client</td><td class="r bold">{{ $order->customer->fullName() }}</td></tr>
            <tr><td class="muted">Téléphone</td><td class="r">{{ $order->customer->phone }}</td></tr>
            <tr><td class="muted">Déposé le</td><td class="r">{{ $order->dropped_off_at->format('d/m/Y à H:i') }}</td></tr>
            @if ($order->expected_at)
                <tr><td class="muted">Retrait prévu</td><td class="r bold">{{ $order->expected_at->translatedFormat('D j M Y') }}</td></tr>
            @endif
            @if ($order->recovered_at)
                <tr><td class="muted">Récupéré le</td><td class="r">{{ $order->recovered_at->format('d/m/Y à H:i') }}</td></tr>
            @endif
            @if ($order->creator)
                <tr><td class="muted">Reçu par</td><td class="r">{{ $order->creator->name }}</td></tr>
            @endif
        </table>

        <hr class="rule">

        <table class="items">
            @foreach ($order->items as $item)
                <tr>
                    <td class="qty">{{ $item->quantity }} ×</td>
                    <td>{{ $item->name }}@if ($item->quantity > 1)<br><span class="muted small">{{ $fcfa($item->unit_price_fcfa) }} F l'unité</span>@endif</td>
                    <td class="r">{{ $fcfa($item->subtotal_fcfa) }} F</td>
                </tr>
            @endforeach
        </table>

        <hr class="rule">

        <table>
            <tr class="total"><td>Total</td><td class="r">{{ $fcfa($order->total_fcfa) }} F</td></tr>
        </table>

        <div class="state">{{ $paymentState->label() }}</div>

        <table>
            @foreach ($order->payments as $payment)
                <tr>
                    <td class="muted small">{{ $payment->paid_at->format('d/m/Y') }} · {{ $payment->method->label() }}</td>
                    <td class="r small">{{ $fcfa($payment->amount_fcfa) }} F</td>
                </tr>
            @endforeach
            <tr><td>Déjà payé</td><td class="r">{{ $fcfa($order->amount_paid_fcfa) }} F</td></tr>
            <tr class="balance">
                <td>{{ $order->recovered_at ? 'Reste dû' : 'Reste à payer au retrait' }}</td>
                <td class="r">{{ $balance > 0 ? $fcfa($balance).' F' : '0 F' }}</td>
            </tr>
        </table>

        @if ($order->notes)
            <div class="note small"><span class="bold">Note :</span> {{ $order->notes }}</div>
        @endif

        <hr class="rule">

        <div class="footer">
            Présentez ce reçu pour retirer vos vêtements.<br>
            Suivez votre commande dans l'application PressLink<br>
            avec le code pressing <span class="code">{{ $pressing->code }}</span>.<br>
            Merci de votre confiance !
        </div>
        <div class="footer small">Imprimé le {{ now()->format('d/m/Y à H:i') }}</div>
    </div>

    @if ($autoPrint && ! $isPdf)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endif
</body>
</html>
