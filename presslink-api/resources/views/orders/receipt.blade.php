@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $isBatch = $orders->count() > 1;
    $payment ??= null;
    $share ??= null;
    $sizeUrl = fn (?string $size) => request()->fullUrlWithQuery(['taille' => $size, 'imprimer' => null]);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isBatch ? $orders->count().' reçus' : ($payment ? ($payment->isRefund() ? 'Reçu de remboursement ' : 'Reçu de paiement ') : 'Reçu ').$orders->first()->order_number }} · {{ $orders->first()->pressing->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: {!! $isPdf ? '"DejaVu Sans", sans-serif' : 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif' !!};
            color: #0f172a;
            background: {{ $isPdf ? '#ffffff' : '#e2e8f0' }};
            font-size: {{ $isPdf ? '10.5px' : ($size === 'ticket' ? '12px' : '12.5px') }};
            line-height: {{ $isPdf ? '1.3' : '1.4' }};
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
        .sheet + .sheet { page-break-before: always; break-before: page; }
        .center { text-align: center; }
        .muted { color: #64748b; }
        .small { font-size: 0.85em; }
        .bold { font-weight: bold; }
        .tiny { font-size: 0.75em; }
        .head td { vertical-align: middle; }
        .head-logo { width: 1%; padding-right: 8px; }
        .logo { display: block; max-height: {{ $size === 'ticket' ? '34px' : '42px' }}; max-width: {{ $size === 'ticket' ? '60px' : '110px' }}; }
        .pressing-name { font-size: {{ $size === 'ticket' ? '1.2em' : '1.35em' }}; font-weight: bold; margin: 0; }
        .label { font-size: 0.72em; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; }
        .hero-wrap { margin-top: 12px; }
        .hero-wrap td { vertical-align: middle; }
        .hero {
            @if ($size === 'ticket')
                border: 2px solid #0f172a; border-radius: 8px; padding: 8px 10px; text-align: center;
            @else
                background: #f5f7fc; border-radius: 12px; padding: 12px 16px;
            @endif
        }
        .hero-number { margin: 2px 0; font-size: {{ $size === 'ticket' ? '2.1em' : '2.9em' }}; font-weight: bold; letter-spacing: -0.5px; line-height: 1.05; }
        .hero-qr { width: 30mm; padding-left: 12px; text-align: center; }
        .hero-qr .qr { width: {{ $isPdf ? '22mm' : '26mm' }}; height: {{ $isPdf ? '22mm' : '26mm' }}; }
        .keys { margin: 10px 0 6px; {{ $size === 'ticket' ? 'text-align: center;' : '' }} }
        .keys td { width: 33%; }
        .key { font-size: 1.25em; font-weight: bold; }
        .key--accent { color: #2563eb; }
        .qr-row td { vertical-align: middle; }
        .qr-cell { width: 1%; padding-right: 8px; }
        .qr-cell .qr { width: 18mm; height: 18mm; display: block; }
        .talon {
            position: relative; margin-top: 14px; padding-top: 12px; border-top: 2px dashed #64748b;
            text-align: center; page-break-inside: avoid;
            @unless ($isPdf)
                margin-left: {{ $size === 'ticket' ? '-5mm' : '-11mm' }}; margin-right: {{ $size === 'ticket' ? '-5mm' : '-11mm' }};
            @endunless
        }
        .cut { position: absolute; top: -0.8em; left: 6mm; padding: 0 4px; background: #fff; color: #64748b; }
        .talon-number { margin: 2px 0; font-size: {{ $size === 'ticket' ? '1.9em' : '2.4em' }}; font-weight: bold; letter-spacing: -0.5px; }
        .rule { border: 0; border-top: 1px dashed #94a3b8; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        td.r { text-align: right; white-space: nowrap; }
        .items { border-top: 1px solid #e2e8f0; }
        .items td { padding: {{ $isPdf ? '3px' : '5px' }} 0; border-bottom: 1px solid #f1f5f9; }
        .items .qty { width: 1%; color: #64748b; white-space: nowrap; padding-right: 8px; }
        .total td { font-size: 1.2em; font-weight: bold; padding-top: 6px; }
        .state {
            margin: 10px 0 6px; padding: 6px 8px; text-align: center; font-weight: bold; letter-spacing: 1px;
            text-transform: uppercase; font-size: 0.9em; border-radius: 4px; border: 1px solid currentColor;
        }
        .state--payee { color: #047857; background: #ecfdf5; }
        .state--acompte { color: #b45309; background: #fffbeb; }
        .state--a_payer { color: #334155; background: #f1f5f9; }
        .balance td { font-size: 1.15em; font-weight: bold; padding-top: 4px; }
        .note { margin-top: 8px; padding: 6px 8px; background: #f8fafc; border-left: 3px solid #cbd5e1; }
        .footer { margin-top: 12px; text-align: center; color: #64748b; font-size: 0.85em; }
        .signature { margin-top: 10px; text-align: center; color: #94a3b8; font-size: 0.75em; }
        .signature img { height: 13px; width: auto; vertical-align: middle; margin-left: 4px; }
        .code { font-family: "DejaVu Sans Mono", ui-monospace, monospace; font-weight: bold; color: #0f172a; letter-spacing: 1px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0 auto; box-shadow: none; border-radius: 0; }
        }
        @page { size: {{ $size === 'ticket' ? '80mm auto' : 'A5' }}; margin: {{ $isPdf ? '10mm 12mm' : ($size === 'ticket' ? '0' : '8mm') }}; }
    </style>
</head>
<body>
    @unless ($isPdf)
        <div class="toolbar">
            <button type="button" class="primary" onclick="window.print()">Imprimer{{ $isBatch ? ' les '.$orders->count().' reçus' : '' }}</button>
            <a href="{{ $pdfUrl }}">Télécharger le PDF</a>
            @if ($share)
                <button type="button" id="share-receipt">Partager</button>
            @endif
            <span class="sep"></span>
            <a href="{{ $sizeUrl(null) }}" class="{{ $size === 'ticket' ? 'on' : '' }}">Ticket 80 mm</a>
            <a href="{{ $sizeUrl('a5') }}" class="{{ $size === 'a5' ? 'on' : '' }}">Feuille A5</a>
        </div>
    @endunless

    @if ($payment)
        @include('orders._payment-receipt-sheet', ['order' => $orders->first(), 'pressing' => $orders->first()->pressing, 'payment' => $payment])
    @else
        @foreach ($orders as $order)
            @include('orders._receipt-sheet', ['order' => $order, 'pressing' => $order->pressing])
        @endforeach
    @endif

    @if ($share && ! $isPdf)
        @include('orders._share-script')
        <script>
            const receiptShare = @json([...$share, 'phone' => $orders->first()->customer->phone]);
            presslinkShare.prefetch(receiptShare);
            document.getElementById('share-receipt').addEventListener('click', () => presslinkShare(receiptShare));
        </script>
    @endif

    @if ($autoPrint && ! $isPdf)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endif
</body>
</html>
