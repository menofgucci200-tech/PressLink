<?php

namespace App\Http\Controllers\Api\V1;

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
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()
            ->with(['pressing', 'items'])
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        $order->load(['pressing', 'items', 'statusHistory', 'issues', 'payments:id,order_id,amount_fcfa,method,paid_at']);

        // Reçu de dépôt : à ouvrir, télécharger ou partager depuis l'appli.
        $storageFee = $order->storageFee();

        return response()->json([...$order->toArray(), 'storage_fee' => $storageFee === null ? null : [
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
