<?php

namespace Tests\Feature;

use App\Livewire\Admin\Health\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Santé de la plateforme : page de diagnostic de la console et temps serveur
 * dans l'en-tête Server-Timing.
 */
class PlatformHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_super_admin_sees_the_health_of_the_platform(): void
    {
        DB::table('failed_jobs')->insert(['uuid' => 'a', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Boom', 'failed_at' => now()]);
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $this->get(route('admin.health.index'))->assertOk()
            ->assertSee('Santé de la plateforme')
            ->assertSee('OPcache')
            ->assertSee('Temps d&#039;aller-retour', false)
            ->assertSee('Tâche planifiée (cron)')
            ->assertSee('Envois en échec (7 derniers jours)');

        Livewire::test(Index::class)->call('$refresh')->assertOk();
    }

    public function test_the_health_page_is_reserved_to_the_super_admin(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.health.index'))->assertForbidden();
    }

    public function test_every_response_tells_the_server_time(): void
    {
        $this->get('/')->assertOk()->assertHeader('Server-Timing');
        $this->assertMatchesRegularExpression('/^app;dur=[\d.]+, db;dur=[\d.]+;desc="\d+ requetes"$/', $this->get('/up')->headers->get('Server-Timing'));
    }
}
