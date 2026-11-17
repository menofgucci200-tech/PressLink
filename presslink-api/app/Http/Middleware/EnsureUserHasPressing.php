<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages de l'espace pressing : réservées aux comptes rattachés à un pressing.
 * Un super admin (sans pressing) est renvoyé vers sa console au lieu de
 * tomber sur une erreur.
 */
class EnsureUserHasPressing
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->currentPressing()) {
            return match (true) {
                (bool) $user->is_super_admin => redirect()->route('admin.dashboard'),
                $user->isActiveSalesAgent() => redirect()->route('sales.dashboard'),
                default => abort(403),
            };
        }

        return $next($request);
    }
}
