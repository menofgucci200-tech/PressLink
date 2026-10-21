<?php

namespace App\Http\Controllers;

use App\Actions\Offline\ApplyOfflineOperations;
use App\Enums\Feature;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\ServicePrice;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Comptoir hors connexion (formules Business et Premium, ou option) :
 *  - la page, gardée sur le PC par le service worker (public/espace-sw.js)
 *    et ouverte à la place du tableau de bord quand Internet coupe ;
 *  - la copie des données dont elle a besoin (catalogue, prix, clients,
 *    commandes en cours), reprise régulièrement tant qu'on est en ligne ;
 *  - l'envoi des opérations faites hors connexion (ApplyOfflineOperations).
 */
class OfflineCounterController extends Controller
{
    /** Commandes récentes gardées sur le PC, en plus de celles en cours. */
    private const RECENT_DAYS = 14;

    private const MAX_ORDERS = 800;

    /** Droit de magasinage précalculé pour les jours à venir (remise hors connexion). */
    private const STORAGE_FEE_DAYS = 7;

    private const MAX_OPERATIONS = 200;

    public function show(Request $request): View|RedirectResponse
    {
        $pressing = $this->pressing($request);

        if (! $request->user()->pressingHasFeature($pressing, Feature::Offline)) {
            abort_unless($request->user()->may(Permission::SubscriptionManage, $pressing), 403);

            return redirect()->route('subscription.show', ['option' => Feature::Offline->value]);
        }

        return view('comptoir.show', ['pressing' => $pressing, 'user' => $request->user()]);
    }

    public function snapshot(Request $request): JsonResponse
    {
        $pressing = $this->pressing($request);
        $user = $request->user();
        abort_unless($user->pressingHasFeature($pressing, Feature::Offline), 403);

        $pieces = $pressing->services()->where('is_active', true)->with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('name')])->orderBy('name')->get();

        $orders = $pressing->orders()
            ->where(fn ($q) => $q->whereIn('status', [OrderStatus::Recue->value, OrderStatus::Traitement->value, OrderStatus::Attente->value, OrderStatus::Prete->value])
                ->orWhere('dropped_off_at', '>=', now()->subDays(self::RECENT_DAYS)))
            ->with(['items', 'payments'])
            ->latest('dropped_off_at')
            ->limit(self::MAX_ORDERS)
            ->get();

        $hasStoragePolicy = $pressing->storageFeePolicy() !== null;

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'csrf' => csrf_token(),
            'pressing' => [
                'id' => $pressing->id,
                'name' => $pressing->name,
                'code' => $pressing->code,
                'address' => collect([$pressing->address, $pressing->city])->filter()->implode(', '),
                'phone' => $pressing->phone ? PhoneNumber::format($pressing->phone) : null,
                'terms' => $pressing->terms,
                'storage_clause' => $pressing->storageFeeClause(),
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'can' => [
                    'create' => $user->may(Permission::OrdersCreate, $pressing),
                    'pay' => $user->may(Permission::PaymentsRecord, $pressing),
                    'workshop' => $user->may(Permission::OrdersWorkshop, $pressing),
                    'waive' => $user->may(Permission::StorageFeeWaive, $pressing),
                ],
            ],
            'prestations' => $pressing->prestations()->where('is_active', true)->ordered()->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'pieces' => $pieces->map(fn ($piece) => [
                'id' => $piece->id,
                'name' => $piece->name,
                'variants' => $piece->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->values(),
            ])->values(),
            'prices' => ServicePrice::whereIn('service_id', $pieces->pluck('id'))->get(['service_id', 'service_variant_id', 'prestation_id', 'price_fcfa'])
                ->map(fn ($price) => [$price->service_id, $price->service_variant_id, $price->prestation_id, (int) $price->price_fcfa])->values(),
            'customers' => $pressing->customers()->orderBy('first_name')->get(['customers.id', 'first_name', 'last_name', 'phone'])
                ->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->fullName(), 'phone' => $c->phone])->values(),
            'orders' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->order_number,
                'customer_id' => $order->customer_id,
                'status' => $order->status->value,
                'total' => (int) $order->total_fcfa,
                'paid' => (int) $order->amount_paid_fcfa,
                'dropped_off_at' => $order->dropped_off_at?->toIso8601String(),
                'expected_at' => $order->expected_at?->format('Y-m-d'),
                'notes' => $order->notes,
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->name, 'quantity' => (int) $item->quantity,
                    'unit' => (int) $item->unit_price_fcfa, 'subtotal' => (int) $item->subtotal_fcfa,
                ])->values(),
                'payments' => $order->payments->map(fn ($payment) => [
                    'amount' => (int) $payment->amount_fcfa, 'method' => $payment->method->value, 'at' => $payment->paid_at?->toIso8601String(),
                ])->values(),
                'storage_fees' => $hasStoragePolicy && $order->status === OrderStatus::Prete ? $this->storageFees($order) : null,
            ])->values(),
            'payment_methods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()])->values(),
        ]);
    }

    public function sync(Request $request, ApplyOfflineOperations $apply): JsonResponse
    {
        $data = $request->validate([
            'pressing_id' => ['required', 'integer'],
            'operations' => ['present', 'array', 'max:'.self::MAX_OPERATIONS],
            'operations.*' => ['array'],
        ]);

        // Opérations faites dans un pressing donné : envoyées à ce pressing,
        // même si l'utilisateur a changé de pressing actif entre-temps.
        $pressing = Pressing::find($data['pressing_id']);
        abort_unless($pressing !== null && $request->user()->belongsToPressing($pressing), 403);

        return response()->json([
            'results' => $apply->handle($pressing, $request->user(), $data['operations']),
        ]);
    }

    private function pressing(Request $request): Pressing
    {
        $pressing = $request->user()->currentPressing();
        abort_if($pressing === null, 403);

        return $pressing;
    }

    /** @return array<string, int> date (AAAA-MM-JJ) → droit de magasinage dû ce jour-là */
    private function storageFees(Order $order): array
    {
        $fees = [];
        for ($day = 0; $day < self::STORAGE_FEE_DAYS; $day++) {
            $at = Carbon::today()->addDays($day);
            $fees[$at->format('Y-m-d')] = (int) ($order->storageFee($at)['amount'] ?? 0);
        }

        return $fees;
    }
}
