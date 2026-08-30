<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckPhoneRequest;
use App\Http\Requests\Auth\LoginCustomerRequest;
use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Authentification client — Cahier §3.1 (revu le 26/08) :
 * 1. Le client saisit son numéro de téléphone.
 * 2. Si un compte existe déjà → on demande le mot de passe (login).
 * 3. Sinon → formulaire d'inscription complet (register).
 */
class CustomerAuthController extends Controller
{
    public function checkPhone(CheckPhoneRequest $request): JsonResponse
    {
        $exists = Customer::where('phone', $request->string('phone')->toString())->exists();

        return response()->json(['exists' => $exists]);
    }

    public function login(LoginCustomerRequest $request): JsonResponse
    {
        $customer = Customer::where('phone', $request->string('phone')->toString())->first();

        if ($customer === null || ! Hash::check($request->string('password')->toString(), $customer->password)) {
            return response()->json([
                'message' => 'Numéro de téléphone ou mot de passe incorrect.',
            ], 422);
        }

        $customer->update(['last_login_at' => now()]);

        return response()->json([
            'token' => $customer->createToken('customer-app')->plainTextToken,
            'customer' => $this->formatCustomer($customer),
        ]);
    }

    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create([
            'phone' => $request->string('phone')->toString(),
            'first_name' => $request->string('first_name')->toString(),
            'last_name' => $request->string('last_name')->toString(),
            'gender' => $request->string('gender')->toString(),
            'email' => $request->string('email')->toString() ?: null,
            'password' => $request->string('password')->toString(),
            'phone_verified_at' => now(),
            'last_login_at' => now(),
            'is_active' => true,
        ]);

        return response()->json([
            'token' => $customer->createToken('customer-app')->plainTextToken,
            'customer' => $this->formatCustomer($customer),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->formatCustomer($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'gender' => $customer->gender?->value,
            'photo_url' => $customer->photo_url,
        ];
    }
}
