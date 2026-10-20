<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Support\ReceiptLinks;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reçus de commande, à remettre au client au dépôt : page imprimable
 * (ticket 80 mm ou A5) et PDF à télécharger ou à envoyer. Ils reprennent les
 * articles, le total et l'état du paiement (payé, acompte, reste à payer).
 * Plusieurs commandes cochées dans la liste s'impriment en une fois, un reçu
 * par page.
 */
class OrderReceiptController extends Controller
{
    /** Garde-fou pour l'impression groupée. */
    public const MAX_BATCH = 100;

    public function show(Request $request, Order $order, ?string $format = null): Response
    {
        abort_unless($request->user()->can('view', $order), 403);

        return $this->respond($request, new Collection([$order]), $format, "recu-{$order->order_number}.pdf");
    }

    /** Reçu d'un paiement (ou d'un remboursement) de la commande. */
    public function payment(Request $request, Order $order, OrderPayment $payment, ?string $format = null): Response
    {
        abort_unless($request->user()->can('view', $order) && $payment->order_id === $order->id, 403);

        return $this->respond($request, new Collection([$order]), $format, ReceiptLinks::paymentFileName($payment), $payment);
    }

    public function batch(Request $request, ?string $format = null): Response
    {
        $pressing = $request->user()->currentPressing();
        abort_unless($pressing !== null, 403);

        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn (string $id) => (int) $id)
            ->filter()
            ->unique()
            ->take(self::MAX_BATCH);

        // Uniquement les commandes du pressing courant (les autres sont ignorées).
        $orders = $pressing->orders()->whereIn('id', $ids)->orderByDesc('id')->get();
        abort_if($orders->isEmpty(), 404);

        return $this->respond($request, $orders, $format, 'recus-'.now()->format('Y-m-d').'.pdf');
    }

    /** @param  Collection<int, Order>  $orders */
    private function respond(Request $request, Collection $orders, ?string $format, string $filename, ?OrderPayment $payment = null): Response
    {
        $orders->load(['pressing', 'customer', 'items', 'payments', 'creator']);
        $payment?->load('recorder');

        $data = [
            'orders' => $orders,
            'payment' => $payment,
            'size' => $request->query('taille') === 'a5' ? 'a5' : 'ticket',
            'autoPrint' => $request->boolean('imprimer'),
        ];

        if ($format === 'pdf') {
            return Pdf::loadView('orders.receipt', [...$data, 'isPdf' => true, 'size' => 'a5'])
                ->setPaper('a5')
                ->download($filename);
        }

        $pdfUrl = match (true) {
            $payment !== null => route('orders.payment-receipt', ['order' => $orders->first(), 'payment' => $payment, 'format' => 'pdf']),
            $orders->count() === 1 => route('orders.receipt', ['order' => $orders->first(), 'format' => 'pdf']),
            default => route('orders.receipts', ['format' => 'pdf', 'ids' => $orders->pluck('id')->implode(',')]),
        };

        // Partage (WhatsApp…) : un lien signé vers le PDF, ouvrable sans connexion.
        $share = match (true) {
            $payment !== null => ['url' => ReceiptLinks::payment($payment), 'text' => ReceiptLinks::paymentShareText($payment), 'file' => ReceiptLinks::paymentFileName($payment)],
            $orders->count() === 1 => ['url' => ReceiptLinks::order($orders->first()), 'text' => ReceiptLinks::orderShareText($orders->first()), 'file' => ReceiptLinks::orderFileName($orders->first())],
            default => null,
        };

        return response()->view('orders.receipt', [...$data, 'isPdf' => false, 'pdfUrl' => $pdfUrl, 'share' => $share]);
    }
}
