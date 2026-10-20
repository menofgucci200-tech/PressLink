<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Support\Facades\URL;

/**
 * Liens de reçus à partager (WhatsApp, SMS…) ou à ouvrir depuis l'appli du
 * client : des liens signés, valables 90 jours, qui ouvrent le PDF sans
 * connexion. Le partage envoie l'un de ces liens (ou le PDF lui-même).
 */
final class ReceiptLinks
{
    public const VALID_DAYS = 90;

    /** Reçu de dépôt de la commande (PDF). */
    public static function order(Order $order, bool $download = false): string
    {
        return URL::temporarySignedRoute('receipts.order', now()->addDays(self::VALID_DAYS),
            array_filter(['order' => $order->id, 'telecharger' => $download ? 1 : null]));
    }

    /** Reçu d'un paiement (ou d'un remboursement), en PDF. */
    public static function payment(OrderPayment $payment, bool $download = false): string
    {
        return URL::temporarySignedRoute('receipts.payment', now()->addDays(self::VALID_DAYS),
            array_filter(['payment' => $payment->id, 'telecharger' => $download ? 1 : null]));
    }

    /** Nom du fichier PDF du reçu de dépôt. */
    public static function orderFileName(Order $order): string
    {
        return "recu-{$order->order_number}.pdf";
    }

    /** Nom du fichier PDF du reçu de paiement : « recu-paiement-PL-145423-2.pdf ». */
    public static function paymentFileName(OrderPayment $payment): string
    {
        return 'recu-'.($payment->isRefund() ? 'remboursement' : 'paiement').'-'.$payment->order->order_number.'-'.self::paymentRank($payment).'.pdf';
    }

    /** Rang du paiement dans la commande (1er, 2e…), pour numéroter son reçu. */
    public static function paymentRank(OrderPayment $payment): int
    {
        return $payment->order->payments()->where(fn ($q) => $q->where('paid_at', '<', $payment->paid_at)
            ->orWhere(fn ($q) => $q->where('paid_at', $payment->paid_at)->where('id', '<=', $payment->id)))->count();
    }

    /** Message qui accompagne le reçu partagé. */
    public static function orderShareText(Order $order): string
    {
        return "Reçu de dépôt {$order->order_number} — {$order->pressing->name}";
    }

    public static function paymentShareText(OrderPayment $payment): string
    {
        return ($payment->isRefund() ? 'Reçu de remboursement' : 'Reçu de paiement').' de '.number_format(abs($payment->amount_fcfa), 0, ',', ' ')
            ." F — commande {$payment->order->order_number}, {$payment->order->pressing->name}";
    }
}
