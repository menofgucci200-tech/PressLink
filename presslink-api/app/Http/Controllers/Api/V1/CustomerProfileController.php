<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerPasswordRequest;
use App\Http\Requests\UpdateCustomerPhoneRequest;
use App\Http\Requests\UpdateCustomerPhotoRequest;
use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Models\Customer;
use App\Support\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Édition du profil client — Profil §Personnalisation (26/08).
 */
class CustomerProfileController extends Controller
{
    public function update(UpdateCustomerProfileRequest $request): JsonResponse
    {
        $customer = $request->user();

        // Interrupteur « Aussi par email » : seul ce réglage change, le
        // reste du profil (genre, e-mail…) n'est pas touché.
        if ($request->isPreferenceOnly()) {
            $customer->update(['email_notifications' => $request->boolean('email_notifications')]);

            return response()->json($customer->fresh());
        }

        $customer->update([
            'first_name' => $request->string('first_name')->toString(),
            'last_name' => $request->string('last_name')->toString(),
            'gender' => $request->string('gender')->toString(),
            'email' => $request->string('email')->toString() ?: null,
            ...($request->has('email_notifications') ? ['email_notifications' => $request->boolean('email_notifications')] : []),
        ]);

        return response()->json($customer->fresh());
    }

    public function updatePassword(UpdateCustomerPasswordRequest $request): JsonResponse
    {
        $customer = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $customer->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
            ], 422);
        }

        $customer->update(['password' => $request->string('password')->toString(), 'must_change_password' => false]);

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }

    /**
     * Nouveau numéro de téléphone (identifiant de connexion) : vérifié par
     * le mot de passe actuel, la session en cours reste ouverte.
     */
    public function updatePhone(UpdateCustomerPhoneRequest $request): JsonResponse
    {
        $customer = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $customer->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
                'errors' => ['current_password' => ['Le mot de passe actuel est incorrect.']],
            ], 422);
        }

        $phone = $request->string('phone')->toString();

        if ($phone === $customer->phone) {
            return response()->json([
                'message' => 'C\'est déjà votre numéro actuel.',
                'errors' => ['phone' => ['C\'est déjà votre numéro actuel.']],
            ], 422);
        }

        $customer->update(['phone' => $phone, 'phone_verified_at' => now()]);

        return response()->json($customer->fresh());
    }

    public function updatePhoto(UpdateCustomerPhotoRequest $request): JsonResponse
    {
        $customer = $request->user();

        if ($customer->photo_path) {
            Storage::disk('public')->delete($customer->photo_path);
        }

        $path = ImageOptimizer::store($request->file('photo'), 'customers');
        $customer->update(['photo_path' => $path]);

        return response()->json($customer->fresh());
    }

    public function deletePhoto(Request $request): JsonResponse
    {
        $customer = $request->user();

        if ($customer->photo_path) {
            Storage::disk('public')->delete($customer->photo_path);
            $customer->update(['photo_path' => null]);
        }

        return response()->json($customer->fresh());
    }

    public function updateOnesignalPlayerId(Request $request): JsonResponse
    {
        $request->validate(['player_id' => ['required', 'string', 'max:255']]);

        $playerId = $request->string('player_id')->toString();
        $customer = $request->user();

        // Un téléphone ne reçoit les notifications que d'un seul compte : le
        // client qui s'y connecte en dernier. Un autre compte qui gardait cet
        // identifiant (téléphone partagé, ancien compte) le perd.
        Customer::query()
            ->where('onesignal_player_id', $playerId)
            ->whereKeyNot($customer->getKey())
            ->update(['onesignal_player_id' => null]);

        $customer->update(['onesignal_player_id' => $playerId]);

        return response()->json(['message' => 'Identifiant OneSignal enregistré.']);
    }
}
