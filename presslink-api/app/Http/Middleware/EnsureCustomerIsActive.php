<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque l'API pour un client désactivé depuis la console Super Admin :
 * le jeton utilisé est révoqué et l'application reçoit un 403, qui la
 * renvoie vers l'écran de connexion.
 */
class EnsureCustomerIsActive
{
    public const MESSAGE = 'Votre compte a été désactivé. Contactez votre pressing ou le support PressLink.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof Customer && ! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => self::MESSAGE], 403);
        }

        return $next($request);
    }
}
