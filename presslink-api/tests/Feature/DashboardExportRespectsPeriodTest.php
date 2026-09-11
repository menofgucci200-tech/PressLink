<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Dashboard;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'export du Dashboard exportait toujours "toutes les commandes", en
 * ignorant complètement la période sélectionnée (Aujourd'hui/7j/30j/
 * personnalisée) — vérifie que les liens d'export portent bien les
 * mêmes bornes de dates que celles utilisées pour les 4 cartes stats.
 */
class DashboardExportRespectsPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(Pressing $pressing): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return $user;
    }

    public function test_today_period_exports_only_todays_date_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00'));

        $pressing = Pressing::factory()->create();
        $admin = $this->makeAdmin($pressing);

        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->assertSet('period', 'today')
            ->assertViewHas('exportParams', [
                'status' => null,
                'search' => null,
                'date_from' => '2026-09-10',
                'date_to' => '2026-09-10',
            ]);

        Carbon::setTestNow();
    }

    public function test_7d_period_exports_the_matching_date_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00'));

        $pressing = Pressing::factory()->create();
        $admin = $this->makeAdmin($pressing);

        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->call('setPeriod', '7d')
            ->assertViewHas('exportParams', [
                'status' => null,
                'search' => null,
                'date_from' => '2026-09-04',
                'date_to' => '2026-09-10',
            ]);

        Carbon::setTestNow();
    }
}
