<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pages d'erreur personnalisées (404, 403, 419, 500…) : pages web aux
 * couleurs de PressLink, réponses JSON conservées pour l'API.
 */
class ErrorPagesTest extends TestCase
{
    public function test_unknown_page_shows_the_custom_404(): void
    {
        $this->get('/cette-page-nexiste-pas')
            ->assertNotFound()
            ->assertSee('Page introuvable')
            ->assertSee('Retour à l\'accueil', false);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function errors(): array
    {
        return [
            '403' => [403, 'Accès refusé'],
            '419' => [419, 'Session expirée'],
            '429' => [429, 'Trop de tentatives'],
            '500' => [500, 'Une erreur est survenue'],
            '503' => [503, 'Maintenance en cours'],
        ];
    }

    #[DataProvider('errors')]
    public function test_each_error_has_its_own_page(int $status, string $title): void
    {
        Route::get('/_test-erreur', fn () => abort($status));

        $this->get('/_test-erreur')->assertStatus($status)->assertSee($title);
    }

    public function test_api_errors_stay_json(): void
    {
        $this->getJson('/api/v1/introuvable')->assertNotFound()->assertJsonStructure(['message']);
    }
}
