{{-- Un reçu (une commande), avec son talon à agrafer au sac de linge ; voir orders/receipt.blade.php pour la page et l'impression. --}}
@php
    $paymentState = $order->paymentStatus();
    $balance = $order->balance_due_fcfa;
    $pieces = (int) $order->items->sum('quantity');
    $piecesLabel = $pieces.' pièce'.($pieces > 1 ? 's' : '');
    $pickupLabel = $order->expected_at?->translatedFormat('D j M');
@endphp
    <div class="sheet">
        @include('orders._receipt-header', ['title' => 'Reçu de dépôt', 'date' => $order->dropped_off_at->format('d/m/Y à H:i')])

        <table class="hero-wrap">
            <tr>
                <td>
                    <div class="hero">
                        <div class="label">Commande</div>
                        <p class="hero-number">{{ $order->order_number }}</p>
                        <div><span class="bold">{{ $order->customer->fullName() }}</span> <span class="muted">· {{ \App\Support\PhoneNumber::format($order->customer->phone) }}</span></div>
                    </div>
                </td>
                @if ($size !== 'ticket')
                    <td class="hero-qr">
                        @include('orders._receipt-qr')
                        <div class="muted tiny">Scannez pour suivre<br>votre commande</div>
                    </td>
                @endif
            </tr>
        </table>

        <table class="keys">
            <tr>
                @if ($order->recovered_at)
                    <td><div class="label">{{ $size === 'ticket' ? 'Récupéré' : 'Récupéré le' }}</div><div class="key">{{ $order->recovered_at->format('d/m/Y') }}</div></td>
                @else
                    <td><div class="label">{{ $size === 'ticket' ? 'Retrait' : 'Retrait prévu' }}</div><div class="key key--accent">{{ $pickupLabel ?? '—' }}</div></td>
                @endif
                <td><div class="label">Pièces</div><div class="key">{{ $pieces }}</div></td>
                <td><div class="label">{{ $size === 'ticket' ? 'À payer' : 'Reste à payer' }}</div><div class="key">{{ $fcfa(max(0, $balance)) }} F</div></td>
            </tr>
        </table>

        <table class="items">
            @foreach ($order->items as $item)
                <tr>
                    <td class="qty">{{ $item->quantity }} ×</td>
                    <td>{{ $item->name }}@if ($item->quantity > 1)<br><span class="muted small">{{ $fcfa($item->unit_price_fcfa) }} F l'unité</span>@endif</td>
                    <td class="r">{{ $fcfa($item->subtotal_fcfa) }} F</td>
                </tr>
            @endforeach
        </table>

        <table>
            <tr class="total"><td>Total</td><td class="r">{{ $fcfa($order->total_fcfa) }} F</td></tr>
        </table>

        <div class="state state--{{ $paymentState->value }}">{{ $paymentState->label() }}</div>

        <table>
            @foreach ($order->payments as $payment)
                <tr>
                    <td class="muted small">{{ $payment->paid_at->format('d/m/Y') }} · {{ $payment->isRefund() ? 'Remboursement · ' : '' }}{{ $payment->method->label() }}</td>
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

        @if ($pressing->terms || $pressing->storageFeeClause())
            <hr class="rule">
            <div class="small">
                <div class="bold" style="margin-bottom: 3px;">Conditions</div>
                @if ($pressing->terms)
                    <div class="muted" style="white-space: pre-line;">{{ $pressing->terms }}</div>
                @endif
                @if ($pressing->storageFeeClause())
                    <div class="muted" style="margin-top: 3px;">{{ $pressing->storageFeeClause() }}</div>
                @endif
            </div>
        @endif

        <hr class="rule">

        @if ($size === 'ticket')
            <table class="qr-row">
                <tr>
                    <td class="qr-cell">@include('orders._receipt-qr')</td>
                    <td class="muted small">Scannez pour suivre votre commande dans l'application PressLink (presslink.org/app) avec votre numéro de téléphone.</td>
                </tr>
            </table>
        @endif

        <div class="footer">
            Présentez ce reçu pour retirer vos vêtements.<br>
            @if ($size !== 'ticket')
                Suivez votre commande dans l'application PressLink (presslink.org/app) avec votre numéro de téléphone.<br>
            @endif
            Merci de votre confiance !
        </div>
        <div class="footer small">{{ $order->creator ? 'Reçu par '.$order->creator->name.' · ' : '' }}Imprimé le {{ now()->format('d/m/Y à H:i') }}</div>
        @include('orders._receipt-signature')

        @unless ($order->recovered_at || $isPdf)
            {{-- Talon (papier seulement, pas dans le PDF envoyé au client) : le pressing le détache et l'agrafe au sac. --}}
            <div class="talon">
                <span class="cut">✂</span>
                <div class="label">Talon · à agrafer au sac de linge</div>
                <p class="talon-number">{{ $order->order_number }}</p>
                <div><span class="bold">{{ $order->customer->fullName() }}</span> · {{ $piecesLabel }}{{ $pickupLabel ? ' · retrait '.$pickupLabel : '' }}</div>
                <div class="muted small">{{ $pressing->name }}</div>
            </div>
        @endunless
    </div>
