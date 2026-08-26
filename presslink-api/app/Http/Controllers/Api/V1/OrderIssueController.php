<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderIssueRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Signalement d'un problème par le client sur l'une de ses commandes
 * (ex. article manquant, article qui ne lui appartient pas).
 */
class OrderIssueController extends Controller
{
    public function store(StoreOrderIssueRequest $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        $issue = $order->issues()->create([
            'customer_id' => $request->user()->id,
            'category' => $request->string('category')->toString(),
            'description' => $request->string('description')->toString() ?: null,
        ]);

        return response()->json($issue, 201);
    }

    public function index(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        return response()->json($order->issues);
    }
}
