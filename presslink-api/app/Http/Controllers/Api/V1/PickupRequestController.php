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
 * Demandes de ramassage du client (mode « Mon pressing ») : créneaux
 * proposés, envoi, photos du linge, suivi et annulation.
 */
class PickupRequestController extends Controller
{
    public function __construct(private PickupRequests $pickups) {}

    /** Jours et créneaux proposés par l'un des pressings du client. */
    public function availability(Request $request, Pressing $pressing): JsonResponse
    {
        abort_unless($request->user()->pressings()->whereKey($pressing->id)->exists(), 404);

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
        $pressing = Pressing::find($request->integer('pressing_id'));
        $address = CustomerAddress::where('customer_id', $customer->id)->find($request->integer('address_id'));
        abort_if($pressing === null || $address === null, 404);

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
