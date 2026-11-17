<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espace commercial (/commercial) : réservé aux commerciaux actifs. Un
 * commercial suspendu est déconnecté avec un message.
 */
class EnsureSalesAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $agent = $request->user()?->salesAgent;

        abort_if($agent === null, 403);

        if (! $agent->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Votre compte commercial est suspendu. Contactez l\'équipe PressLink.']);
        }

        return $next($request);
    }
}
