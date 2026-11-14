<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Deliveries\OrderDeliveries;
use App\Enums\OrderDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\ReceiptLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Commandes du client authentifié — RB-02 : un client ne voit que ses
 * propres commandes.
 */
class OrderController extends Controller
{
    public function __construct(private OrderDeliveries $deliveries) {}

    /**
     * Pagination conservée (anciennes versions de l'appli : 20 par page),
     * mais l'appli peut demander jusqu'à 100 commandes par page et lit
     * toutes les pages : une commande prête plus ancienne que les 20
     * dernières ne doit jamais disparaître de l'appli.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        $orders = $request->user()->orders()
            ->with(['pressing', 'items'])
            ->withExists(['deliveries as delivered' => fn ($query) => $query->where('status', OrderDeliveryStatus::Delivered->value)])
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        $order->load(['pressing', 'items', 'statusHistory', 'issues', 'payments:id,order_id,amount_fcfa,method,paid_at', 'photos']);

        // Reçu de dépôt : à ouvrir, télécharger ou partager depuis l'appli.
        $storageFee = $order->storageFee();

        // Photos des articles prises au dépôt : liens signés, valables 24 h.
        // Ramassage à domicile et livraison (la dernière, sauf une annulation ancienne).
        $delivery = $order->deliveries()->first();
        $homeService = [
            'picked_up' => $order->pickupRequest()->exists(),
            'delivered' => $delivery?->status === OrderDeliveryStatus::Delivered,
            'delivery' => $delivery?->toClientArray(),
            'delivery_offer' => ($offer = $this->deliveries->offer($order))['available'] ? ['fee_fcfa' => $offer['fee_fcfa']] : null,
        ];

        return response()->json([...$order->toArray(), ...$homeService, 'photos' => $order->photos->map->toClientArray()->all(), 'storage_fee' => $storageFee === null ? null : [
            'days' => $storageFee['days'],
            'amount' => $storageFee['amount'],
            'since' => $storageFee['since']->toDateString(),
        ], 'storage_fee_clause' => $order->pressing->storageFeeClause(), 'pressing_contact' => $order->pressing->supportContact(), 'receipt' => [
            'url' => ReceiptLinks::order($order),
            'download_url' => ReceiptLinks::order($order, download: true),
            'file_name' => ReceiptLinks::orderFileName($order),
            'share_text' => ReceiptLinks::orderShareText($order),
        ]]);
    }
}
