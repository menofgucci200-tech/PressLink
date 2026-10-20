<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PressingStatus;
use App\Http\Controllers\Controller;
use App\Models\Pressing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pressings du client authentifié — User Flows §3 : rejoindre un
 * pressing via son code public (ex. PE-4821).
 */
class PressingController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $pressings = $request->user()->pressings()
            ->withCount(['orders' => fn ($q) => $q->where('customer_id', $request->user()->id)])
            ->get();

        $data = $pressings->map(function (Pressing $pressing) {
            $array = $pressing->toArray();
            // Conditions et règlement, et la règle de magasinage en une phrase.
            $array['storage_fee_clause'] = $pressing->storageFeeClause();

            // Services et prix (Repassage, Nettoyage…) : seulement si le pressing les montre à ses clients.
            if ($pressing->show_pricing_to_customers) {
                $array['prestations'] = $pressing->publicCatalog();
            }

            return $array;
        });

        return response()->json($data);
    }

    public function join(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $pressing = Pressing::where('code', mb_strtoupper($request->string('code')->toString()))->first();

        if ($pressing === null) {
            return response()->json([
                'message' => 'Ce code ne correspond à aucun pressing.',
            ], 404);
        }

        if ($pressing->status !== PressingStatus::Active) {
            return response()->json([
                'message' => 'Ce pressing n\'accepte plus de nouveaux clients pour le moment.',
            ], 403);
        }

        $alreadyJoined = $pressing->customers()->where('customers.id', $request->user()->id)->exists();
        $subscription = $pressing->subscription;

        if (! $alreadyJoined && $subscription !== null && ! $subscription->allowsNewClient()) {
            return response()->json([
                'message' => 'Ce pressing a atteint sa capacité maximale de clients pour le moment.',
            ], 403);
        }

        $request->user()->pressings()->syncWithoutDetaching([
            $pressing->id => ['joined_at' => now()],
        ]);

        return response()->json($pressing);
    }

    public function leave(Request $request, Pressing $pressing): JsonResponse
    {
        $request->user()->pressings()->detach($pressing->id);

        return response()->json(['message' => 'Vous avez quitté ce pressing.']);
    }
}
