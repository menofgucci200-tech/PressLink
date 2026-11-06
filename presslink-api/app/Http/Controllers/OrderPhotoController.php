<?php

namespace App\Http\Controllers;

use App\Models\OrderPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert une photo de commande (ou sa miniature). Le lien est signé et limité
 * dans le temps : il n'est remis qu'au pressing et au client de la commande.
 */
class OrderPhotoController extends Controller
{
    public function show(Request $request, OrderPhoto $photo): StreamedResponse
    {
        $path = $request->boolean('miniature') ? $photo->thumb_path : $photo->path;
        abort_unless(Storage::disk(OrderPhoto::DISK)->exists($path), 404);

        return Storage::disk(OrderPhoto::DISK)->response($path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
