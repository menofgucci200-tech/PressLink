<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCustomerAddressRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Support\Communes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Adresses de ramassage et de livraison du client (Profil > Mes adresses).
 * La première adresse est l'adresse par défaut ; une seule l'est à la fois.
 */
class CustomerAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->addressesOf($request->user()));
    }

    /** Communes et villes proposées (liste fermée, regroupée). */
    public function communes(): JsonResponse
    {
        return response()->json(collect(Communes::grouped())
            ->map(fn (array $names, string $group) => ['group' => $group, 'communes' => $names])
            ->values());
    }

    public function store(SaveCustomerAddressRequest $request): JsonResponse
    {
        $customer = $request->user();

        if ($customer->addresses()->count() >= CustomerAddress::MAX_PER_CUSTOMER) {
            return response()->json([
                'message' => 'Vous avez déjà '.CustomerAddress::MAX_PER_CUSTOMER.' adresses. Supprimez-en une pour en ajouter une autre.',
            ], 422);
        }

        DB::transaction(function () use ($request, $customer) {
            $makeDefault = $request->boolean('is_default') || ! $customer->addresses()->exists();
            $address = $customer->addresses()->create([...$request->validated(), 'is_default' => $makeDefault]);
            if ($makeDefault) {
                $this->makeDefault($customer, $address);
            }
        });

        return response()->json($this->addressesOf($customer), 201);
    }

    public function update(SaveCustomerAddressRequest $request, CustomerAddress $address): JsonResponse
    {
        $customer = $request->user();
        abort_unless($address->customer_id === $customer->id, 404);

        DB::transaction(function () use ($request, $customer, $address) {
            // Une adresse par défaut le reste tant qu'une autre ne prend pas sa place.
            $address->update([...$request->validated(), 'is_default' => $address->is_default || $request->boolean('is_default')]);
            if ($address->is_default) {
                $this->makeDefault($customer, $address);
            }
        });

        return response()->json($this->addressesOf($customer));
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $request->user();
        abort_unless($address->customer_id === $customer->id, 404);

        DB::transaction(function () use ($customer, $address) {
            $address->delete();
            if ($address->is_default && ($next = $customer->addresses()->oldest('id')->first())) {
                $this->makeDefault($customer, $next);
            }
        });

        return response()->json($this->addressesOf($customer));
    }

    private function makeDefault(Customer $customer, CustomerAddress $address): void
    {
        $customer->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        $address->forceFill(['is_default' => true])->save();
    }

    /** @return Collection<int, CustomerAddress> */
    private function addressesOf(Customer $customer)
    {
        return $customer->addresses()->orderByDesc('is_default')->orderBy('id')->get();
    }
}
