<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pickups\PickupRequests;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePickupPhotoRequest;
use App\Http\Requests\StorePickupRequestRequest;
use App\Models\CustomerAddress;
use App\Models\PickupRequest;
use App\Models\Pressing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Demandes de ramassage du client : pressings qui passent à son adresse,
 * créneaux, envoi à un pressing ou à toute la zone, photos du linge, suivi
 * et annulation. Le client n'a pas besoin d'être déjà client d'un pressing.
 */
class PickupRequestController extends Controller
{
    public function __construct(private PickupRequests $pickups) {}

    /** Pressings qui passent à l'adresse choisie, et la demande ouverte à la zone. */
    public function options(Request $request): JsonResponse
    {
        $address = CustomerAddress::where('customer_id', $request->user()->id)->find($request->integer('address_id'));
        abort_if($address === null, 404);

        return response()->json($this->pickups->options($request->user(), $address));
    }

    /** Jours et créneaux de ramassage d'un pressing. */
    public function availability(Pressing $pressing): JsonResponse
    {
        return response()->json(['days' => $pressing->pickupAvailability()]);
    }

    public function index(Request $request): JsonResponse
    {
        $requests = PickupRequest::where('customer_id', $request->user()->id)
            ->with(['pressing', 'photos'])
            ->latest()
            ->limit(30)
            ->get();

        return response()->json($requests->map->toClientArray()->values());
    }

    public function show(Request $request, PickupRequest $pickupRequest): JsonResponse
    {
        $this->ensureOwned($request, $pickupRequest);

        return response()->json($pickupRequest->toClientArray());
    }

    public function store(StorePickupRequestRequest $request): JsonResponse
    {
        $customer = $request->user();
        // Sans pressing choisi, la demande part à toute la zone.
        $pressing = $request->filled('pressing_id') ? Pressing::find($request->integer('pressing_id')) : null;
        $address = CustomerAddress::where('customer_id', $customer->id)->find($request->integer('address_id'));
        abort_if(($request->filled('pressing_id') && $pressing === null) || $address === null, 404);

        try {
            $pickupRequest = $this->pickups->create($customer, $pressing, $address, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($pickupRequest->toClientArray(), 201);
    }

    public function storePhoto(StorePickupPhotoRequest $request, PickupRequest $pickupRequest): JsonResponse
    {
        $this->ensureOwned($request, $pickupRequest);

        try {
            $this->pickups->addPhoto($pickupRequest, $request->file('photo'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($pickupRequest->fresh()->toClientArray(), 201);
    }

    public function cancel(Request $request, PickupRequest $pickupRequest): JsonResponse
    {
        $this->ensureOwned($request, $pickupRequest);

        try {
            $this->pickups->cancelByCustomer($pickupRequest);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($pickupRequest->fresh()->toClientArray());
    }

    private function ensureOwned(Request $request, PickupRequest $pickupRequest): void
    {
        abort_unless($pickupRequest->customer_id === $request->user()->id, 404);
    }
}
