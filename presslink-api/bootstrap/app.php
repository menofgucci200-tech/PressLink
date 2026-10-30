<?php

use App\Http\Middleware\EnsureCustomerIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias(['customer.active' => EnsureCustomerIsActive::class]);
        // Deux portails : la console super admin a sa propre page de connexion.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Production : chaque déploiement arrive dans son propre dossier (releases/…)
// et la bascule se fait d'un coup, sans couper le site. Le .env et storage/
// (sessions, journaux, cache) restent communs à toutes les versions : leur
// dossier est indiqué dans le fichier .presslink-shared déposé avec la version.
if (is_file($sharedFile = dirname(__DIR__).'/.presslink-shared')) {
    $sharedPath = rtrim(trim((string) file_get_contents($sharedFile)), '/');
    $app->useEnvironmentPath($sharedPath);
    $app->useStoragePath($sharedPath.'/storage');
}

return $app;
