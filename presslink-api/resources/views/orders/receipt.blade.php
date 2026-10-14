@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $isBatch = $orders->count() > 1;
    $sizeUrl = fn (?string $size) => request()->fullUrlWithQuery(['taille' => $size, 'imprimer' => null]);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isBatch ? $orders->count().' reçus' : 'Reçu '.$orders->first()->order_number }} · {{ $orders->first()->pressing->name }}</title>
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
        .sheet + .sheet { page-break-before: always; break-before: page; }
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
            text-transform: uppercase; font-size: 0.9em; border-radius: 4px; border: 1px solid currentColor;
        }
        .state--payee { color: #047857; background: #ecfdf5; }
        .state--acompte { color: #b45309; background: #fffbeb; }
        .state--a_payer { color: #334155; background: #f1f5f9; }
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
            <button type="button" class="primary" onclick="window.print()">Imprimer{{ $isBatch ? ' les '.$orders->count().' reçus' : '' }}</button>
            <a href="{{ $pdfUrl }}">Télécharger le PDF</a>
            <span class="sep"></span>
            <a href="{{ $sizeUrl(null) }}" class="{{ $size === 'ticket' ? 'on' : '' }}">Ticket 80 mm</a>
            <a href="{{ $sizeUrl('a5') }}" class="{{ $size === 'a5' ? 'on' : '' }}">Feuille A5</a>
        </div>
    @endunless

    @foreach ($orders as $order)
        @include('orders._receipt-sheet', ['order' => $order, 'pressing' => $order->pressing])
    @endforeach

    @if ($autoPrint && ! $isPdf)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endif
</body>
</html>
