<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reçu d'une commande, à remettre au client au dépôt : page imprimable
 * (ticket 80 mm ou A5) et PDF à télécharger ou à envoyer. Il reprend les
 * articles, le total et l'état du paiement (payé, acompte, reste à payer).
 */
class OrderReceiptController extends Controller
{
    public function __invoke(Request $request, Order $order, ?string $format = null): Response
    {
        abort_unless($request->user()->can('view', $order), 403);

        $order->load(['pressing', 'customer', 'items', 'payments', 'creator']);

        $data = [
            'order' => $order,
            'pressing' => $order->pressing,
            'size' => $request->query('taille') === 'a5' ? 'a5' : 'ticket',
            'autoPrint' => $request->boolean('imprimer'),
        ];

        if ($format === 'pdf') {
            return Pdf::loadView('orders.receipt', [...$data, 'isPdf' => true, 'size' => 'a5'])
                ->setPaper('a5')
                ->download("recu-{$order->order_number}.pdf");
        }

        return response()->view('orders.receipt', [...$data, 'isPdf' => false]);
    }
}
