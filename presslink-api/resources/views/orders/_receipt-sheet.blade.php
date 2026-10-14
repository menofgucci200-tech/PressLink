{{-- Un reçu (une commande) ; voir orders/receipt.blade.php pour la page et l'impression. --}}
@php
    $paymentState = $order->paymentStatus();
    $balance = $order->balance_due_fcfa;
    $logo = null;
    if ($pressing->logo_path) {
        // dompdf lit les images sur le disque ; le navigateur, par l'URL.
        $logo = $isPdf
            ? (\Illuminate\Support\Facades\Storage::disk('public')->exists($pressing->logo_path) ? \Illuminate\Support\Facades\Storage::disk('public')->path($pressing->logo_path) : null)
            : $pressing->logo_url;
    }
@endphp
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

        <div class="state state--{{ $paymentState->value }}">{{ $paymentState->label() }}</div>

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
