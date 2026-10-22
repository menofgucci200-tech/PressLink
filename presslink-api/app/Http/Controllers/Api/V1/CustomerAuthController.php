<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureCustomerIsActive;
use App\Http\Requests\Auth\CheckPhoneRequest;
use App\Http\Requests\Auth\LoginCustomerRequest;
use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Authentification client — Cahier §3.1 (revu le 26/08, puis le 19/10) :
 * 1. Le client saisit son numéro de téléphone.
 * 2. S'il a déjà créé son compte → on demande le mot de passe (login).
 * 3. Si un pressing l'a déjà enregistré → inscription pré-remplie avec les
 *    informations du pressing, qu'il confirme ou corrige (register réclame
 *    alors ce compte et ses commandes).
 * 4. Sinon → formulaire d'inscription complet (register).
 */
class CustomerAuthController extends Controller
{
    public function checkPhone(CheckPhoneRequest $request): JsonResponse
    {
        $customer = Customer::where('phone', $request->string('phone')->toString())->first();

        // Compte ouvert par un pressing, en attente du client.
        if ($customer !== null && ! $customer->hasAppAccount()) {
            return response()->json([
                'exists' => false,
                'pending' => true,
                'prefill' => [
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'gender' => $customer->gender?->value,
                ],
                'orders_count' => $customer->orders()->count(),
            ]);
        }

        return response()->json(['exists' => $customer !== null, 'pending' => false]);
    }

    public function login(LoginCustomerRequest $request): JsonResponse
    {
        $customer = Customer::where('phone', $request->string('phone')->toString())->first();

        if ($customer === null || ! Hash::check($request->string('password')->toString(), $customer->password)) {
            return response()->json([
                'message' => 'Numéro de téléphone ou mot de passe incorrect.',
            ], 422);
        }

        // Vérifié après le mot de passe : on ne révèle pas l'état d'un
        // compte à quelqu'un qui ne connaît pas ses identifiants.
        if (! $customer->is_active) {
            return response()->json(['message' => EnsureCustomerIsActive::MESSAGE], 403);
        }

        $customer->update(['last_login_at' => now()]);

        return response()->json([
            'token' => $customer->createToken('customer-app')->plainTextToken,
            'customer' => $this->formatCustomer($customer),
        ]);
    }

    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        $details = [
            'first_name' => $request->string('first_name')->toString(),
            'last_name' => $request->string('last_name')->toString(),
            'gender' => $request->string('gender')->toString(),
            'email' => $request->string('email')->toString() ?: null,
            'password' => $request->string('password')->toString(),
            'must_change_password' => false,
            'last_login_at' => now(),
        ];

        // Un pressing l'a déjà enregistré : il réclame ce compte (et ses
        // commandes). Les informations qu'il confirme l'emportent sur celles
        // saisies par le pressing.
        $pending = Customer::where('phone', $request->string('phone')->toString())->whereNull('password')->first();

        if ($pending !== null && ! $pending->is_active) {
            return response()->json(['message' => EnsureCustomerIsActive::MESSAGE], 403);
        }

        if ($pending !== null) {
            $pending->update($details);
            $customer = $pending;
        } else {
            $customer = Customer::create([
                ...$details,
                'phone' => $request->string('phone')->toString(),
                'phone_verified_at' => now(),
                'is_active' => true,
            ]);
        }

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
        $customer = $request->user();
        $customer->currentAccessToken()->delete();

        // Téléphone partagé : ce téléphone ne doit plus recevoir les
        // notifications de ce compte. On ne détache que l'identifiant de ce
        // téléphone (un autre appareil encore connecté garde le sien).
        $playerId = $request->string('player_id')->toString();
        if ($playerId !== '' && $customer->onesignal_player_id === $playerId) {
            $customer->update(['onesignal_player_id' => null]);
        }

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
            // Réglage « Aussi par email » du profil (activé par défaut).
            'email_notifications' => $customer->email_notifications ?? true,
            'created_at' => $customer->created_at?->toIso8601String(),
            // L'application invite à changer un mot de passe réinitialisé ou par défaut.
            'must_change_password' => (bool) $customer->must_change_password,
        ];
    }
}
