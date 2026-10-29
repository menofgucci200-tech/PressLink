<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pressing de test (« Découvrir avec un pressing fictif ») :
 * - au bout de ses jours d'essai, le visiteur est déconnecté et renvoyé vers
 *   la page du test, qui lui propose de créer son vrai compte ;
 * - pas d'abonnement ni de paiement dans un pressing fictif : ces pages
 *   mènent à la création du vrai compte.
 */
class HandleSandboxPressing
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $pressing = $user && ! $user->is_super_admin ? $user->currentPressing() : null;

        if ($pressing === null || ! $pressing->isSandbox()) {
            return $next($request);
        }

        if ($pressing->sandboxHasExpired()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('sandbox.start')->with('sandbox_ended', true);
        }

        if ($request->routeIs('subscription.*')) {
            return redirect()->route('sandbox.convert')->with('toast', [
                'type' => 'info',
                'message' => 'Pas d\'abonnement dans un pressing de test : créez votre vrai compte pour choisir votre formule.',
            ]);
        }

        return $next($request);
    }
}
