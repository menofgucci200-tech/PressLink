{{-- Reçu d'un paiement (ou d'un remboursement) ; voir orders/receipt.blade.php pour la page et l'impression. --}}
@php
    $isRefund = $payment->isRefund();
    $rank = \App\Support\ReceiptLinks::paymentRank($payment);
    // Situation de la commande juste après ce paiement.
    $paidUntil = (int) $order->payments->filter(fn ($p) => $p->paid_at < $payment->paid_at || ($p->paid_at->equalTo($payment->paid_at) && $p->id <= $payment->id))->sum('amount_fcfa');
    $remaining = $order->status === \App\Enums\OrderStatus::Annulee ? 0 : max(0, (int) $order->total_fcfa - $paidUntil);
@endphp
    <div class="sheet">
        @include('orders._receipt-header', ['title' => $isRefund ? 'Reçu de remboursement' : 'Reçu de paiement', 'date' => $payment->paid_at->format('d/m/Y à H:i')])

        <table class="hero-wrap">
            <tr>
                <td>
                    <div class="hero">
                        <div class="label">{{ $isRefund ? 'Montant remboursé' : 'Montant payé' }}</div>
                        <p class="hero-number">{{ $fcfa(abs((int) $payment->amount_fcfa)) }} F</p>
                        <div><span class="bold">N° {{ $order->order_number.'-'.$rank }}</span> <span class="muted">· {{ $payment->method->label() }}</span></div>
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

        <table>
            <tr><td class="muted">Client</td><td class="r bold">{{ $order->customer->fullName() }}</td></tr>
            <tr><td class="muted">Téléphone</td><td class="r">{{ \App\Support\PhoneNumber::format($order->customer->phone) }}</td></tr>
            <tr><td class="muted">Commande</td><td class="r">{{ $order->order_number }}</td></tr>
            <tr><td class="muted">{{ $isRefund ? 'Remboursé le' : 'Payé le' }}</td><td class="r">{{ $payment->paid_at->format('d/m/Y à H:i') }}</td></tr>
            @if ($payment->recorder)
                <tr><td class="muted">{{ $isRefund ? 'Remboursé par' : 'Encaissé par' }}</td><td class="r">{{ $payment->recorder->name }}</td></tr>
            @endif
        </table>

        <hr class="rule">

        <table>
            <tr><td class="muted">Total de la commande</td><td class="r">{{ $fcfa($order->total_fcfa) }} F</td></tr>
            <tr><td class="muted">Payé à ce jour</td><td class="r">{{ $fcfa($paidUntil) }} F</td></tr>
            <tr class="balance">
                <td>{{ $order->status === \App\Enums\OrderStatus::Annulee ? 'Commande annulée' : 'Reste à payer' }}</td>
                <td class="r">{{ $fcfa($remaining) }} F</td>
            </tr>
        </table>

        @if ($payment->note)
            <div class="note small"><span class="bold">Note :</span> {{ $payment->note }}</div>
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
            @if ($size !== 'ticket')
                Suivez votre commande dans l'application PressLink<br>
                (presslink.org/app) avec votre numéro de téléphone.<br>
            @endif
            Merci de votre confiance !
        </div>
        <div class="footer small">Édité le {{ now()->format('d/m/Y à H:i') }}</div>
        @include('orders._receipt-signature')
    </div>
