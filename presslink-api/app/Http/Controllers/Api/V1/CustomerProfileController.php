<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerPasswordRequest;
use App\Http\Requests\UpdateCustomerPhotoRequest;
use App\Http\Requests\UpdateCustomerProfileRequest;
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

        $customer->update([
            'first_name' => $request->string('first_name')->toString(),
            'last_name' => $request->string('last_name')->toString(),
            'gender' => $request->string('gender')->toString(),
            'email' => $request->string('email')->toString() ?: null,
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

        $customer->update(['password' => $request->string('password')->toString()]);

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }

    public function updatePhoto(UpdateCustomerPhotoRequest $request): JsonResponse
    {
        $customer = $request->user();

        if ($customer->photo_path) {
            Storage::disk('public')->delete($customer->photo_path);
        }

        $path = $request->file('photo')->store('customers', 'public');
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
}
