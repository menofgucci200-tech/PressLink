{{-- Reçu d'un paiement (ou d'un remboursement) ; voir orders/receipt.blade.php pour la page et l'impression. --}}
@php
    $isRefund = $payment->isRefund();
    $rank = \App\Support\ReceiptLinks::paymentRank($payment);
    // Situation de la commande juste après ce paiement.
    $paidUntil = (int) $order->payments->filter(fn ($p) => $p->paid_at < $payment->paid_at || ($p->paid_at->equalTo($payment->paid_at) && $p->id <= $payment->id))->sum('amount_fcfa');
    $remaining = $order->status === \App\Enums\OrderStatus::Annulee ? 0 : max(0, (int) $order->total_fcfa - $paidUntil);
@endphp
    <div class="sheet">
        @include('orders._receipt-header', ['title' => $isRefund ? 'Reçu de remboursement' : 'Reçu de paiement', 'number' => $order->order_number.'-'.$rank])

        <hr class="rule">

        <table>
            <tr><td class="muted">Client</td><td class="r bold">{{ $order->customer->fullName() }}</td></tr>
            <tr><td class="muted">Téléphone</td><td class="r">{{ \App\Support\PhoneNumber::format($order->customer->phone) }}</td></tr>
            <tr><td class="muted">Commande</td><td class="r">{{ $order->order_number }}</td></tr>
            <tr><td class="muted">{{ $isRefund ? 'Remboursé le' : 'Payé le' }}</td><td class="r">{{ $payment->paid_at->format('d/m/Y à H:i') }}</td></tr>
            <tr><td class="muted">Moyen</td><td class="r">{{ $payment->method->label() }}</td></tr>
            @if ($payment->recorder)
                <tr><td class="muted">{{ $isRefund ? 'Remboursé par' : 'Encaissé par' }}</td><td class="r">{{ $payment->recorder->name }}</td></tr>
            @endif
        </table>

        <hr class="rule">

        <table>
            <tr class="total"><td>{{ $isRefund ? 'Montant remboursé' : 'Montant payé' }}</td><td class="r">{{ $fcfa(abs((int) $payment->amount_fcfa)) }} F</td></tr>
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

        <div class="footer">
            Suivez votre commande dans l'application PressLink<br>
            (presslink.org/app) avec votre numéro de téléphone.<br>
            Merci de votre confiance !
        </div>
        <div class="footer small">Édité le {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
