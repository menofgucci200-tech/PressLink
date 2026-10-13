<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderIssueRequest;
use App\Mail\OrderIssueReported;
use App\Models\Order;
use App\Models\OrderIssue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

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

        $this->alertPressingAdmins($issue);

        return response()->json($issue, 201);
    }

    /**
     * Prévient par email les administrateurs actifs du pressing. Envoi
     * différé après la réponse (le client n'attend pas le serveur mail) et
     * sans file d'attente : un échec SMTP est journalisé, jamais bloquant.
     */
    private function alertPressingAdmins(OrderIssue $issue): void
    {
        $emails = $issue->order->pressing->admins()
            ->wherePivot('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->all();

        if ($emails === []) {
            return;
        }

        defer(function () use ($issue, $emails): void {
            try {
                Mail::to($emails)->send(new OrderIssueReported($issue));
            } catch (Throwable $e) {
                Log::warning('Alerte email de signalement non envoyée', ['issue_id' => $issue->id, 'error' => $e->getMessage()]);
            }
        });
    }

    public function index(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        return response()->json($order->issues);
    }
}
