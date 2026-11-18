<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-tête Server-Timing sur chaque réponse : temps passé dans PHP depuis le
 * début de la requête et temps des requêtes SQL. Il distingue, vu de
 * l'extérieur (outils du navigateur, curl), la lenteur de l'application de
 * celle du réseau ou de l'hébergement.
 */
class AddServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $queries = 0;
        $sqlMs = 0.0;
        DB::listen(function (QueryExecuted $query) use (&$queries, &$sqlMs): void {
            $queries++;
            $sqlMs += $query->time;
        });

        $response = $next($request);

        $start = defined('LARAVEL_START') ? LARAVEL_START : (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));
        $appMs = (microtime(true) - $start) * 1000;
        $response->headers->set('Server-Timing', sprintf('app;dur=%.1f, db;dur=%.1f;desc="%d requetes"', $appMs, $sqlMs, $queries));

        return $response;
    }
}
