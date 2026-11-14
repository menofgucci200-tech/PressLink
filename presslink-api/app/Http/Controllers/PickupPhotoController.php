<?php

namespace App\Http\Controllers;

use App\Models\PickupRequestPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert une photo de demande de ramassage (ou sa miniature), par lien signé
 * remis seulement au client et au pressing de la demande.
 */
class PickupPhotoController extends Controller
{
    public function show(Request $request, PickupRequestPhoto $photo): StreamedResponse
    {
        $path = $request->boolean('miniature') ? $photo->thumb_path : $photo->path;
        abort_unless(Storage::disk(PickupRequestPhoto::DISK)->exists($path), 404);

        return Storage::disk(PickupRequestPhoto::DISK)->response($path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
