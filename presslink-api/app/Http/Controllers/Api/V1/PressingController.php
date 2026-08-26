<?php

namespace App\Http\Controllers\Api\V1;

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

        return response()->json($pressings);
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
