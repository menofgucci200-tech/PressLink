<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Deliveries\OrderDeliveries;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderDeliveryRequest;
use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Livraison à domicile d'une commande prête : ce que le pressing propose
 * (frais, jours, créneaux), la demande et son annulation.
 */
class OrderDeliveryController extends Controller
{
    public function __construct(private OrderDeliveries $deliveries) {}

    public function offer(Request $request, Order $order): JsonResponse
    {
        $this->ensureOwned($request, $order);

        return response()->json($this->deliveries->offer($order));
    }

    public function store(StoreOrderDeliveryRequest $request, Order $order): JsonResponse
    {
        $this->ensureOwned($request, $order);
        $address = CustomerAddress::where('customer_id', $request->user()->id)->find($request->integer('address_id'));
        abort_if($address === null, 404);

        try {
            $delivery = $this->deliveries->request($request->user(), $order, $address, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($delivery->toClientArray(), 201);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->ensureOwned($request, $order);
        $delivery = $order->scheduledDelivery;
        if ($delivery === null) {
            return response()->json(['message' => 'Aucune livraison n\'est prévue pour cette commande.'], 422);
        }

        try {
            $this->deliveries->cancelByCustomer($delivery);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($delivery->fresh()->toClientArray());
    }

    private function ensureOwned(Request $request, Order $order): void
    {
        abort_unless($order->customer_id === $request->user()->id, 404);
    }
}
