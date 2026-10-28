<?php

namespace Tests\Feature;

use App\Livewire\Admin\Pressings\Create as AdminPressingsCreate;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use App\Support\LaunchOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Offre de lancement : PressLink est gratuit pour les pressings jusqu'à la
 * date configurée (presslink.launch.free_until). Le site l'annonce tant
 * qu'elle court, puis revient de lui-même à son discours habituel.
 */
class LaunchOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['presslink.launch.free_until' => '2026-11-15']);
    }

    public function test_offer_runs_until_the_end_of_the_configured_day(): void
    {
        $this->travelTo('2026-10-26 09:00:00');
        $this->assertTrue(LaunchOffer::isActive());
        $this->assertSame('15 novembre', LaunchOffer::dateLabel());
        $this->assertSame(21, LaunchOffer::daysLeft());

        $this->travelTo('2026-11-15 23:30:00');
        $this->assertTrue(LaunchOffer::isActive());
        $this->assertSame(1, LaunchOffer::daysLeft());

        $this->travelTo('2026-11-16 00:00:01');
        $this->assertFalse(LaunchOffer::isActive());
        $this->assertSame(0, LaunchOffer::daysLeft());
    }

    public function test_offer_is_off_when_no_date_is_configured(): void
    {
        config(['presslink.launch.free_until' => null]);

        $this->assertFalse(LaunchOffer::isActive());
        $this->assertSame('', LaunchOffer::dateLabel());
    }

    public function test_landing_page_announces_the_launch_and_invites_pressings_to_join(): void
    {
        $this->travelTo('2026-10-26 09:00:00');

        $this->get('/')
            ->assertOk()
            ->assertSee('Lancement officiel')
            ->assertSee('Gratuit jusqu\'au 15 novembre', false)
            ->assertSee('Inscrire mon pressing gratuitement')
            ->assertSee('id="rejoindre"', false)
            ->assertSee('https://wa.me/', false);
    }

    public function test_landing_page_drops_the_offer_once_it_has_ended(): void
    {
        $this->travelTo('2026-11-20 09:00:00');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Lancement officiel')
            ->assertDontSee('15 novembre')
            ->assertSee('Inscrire mon pressing');
    }

    public function test_other_site_pages_show_the_launch_banner_while_the_offer_runs(): void
    {
        $this->travelTo('2026-10-26 09:00:00');
        $this->get(route('site.clients'))->assertOk()->assertSee('PressLink est gratuit pour les pressings jusqu\'au 15 novembre', false);

        $this->travelTo('2026-11-20 09:00:00');
        $this->get(route('site.clients'))->assertOk()->assertDontSee('PressLink est gratuit pour les pressings');
    }

    public function test_new_pressings_get_a_week_of_trial_after_the_end_of_the_offer(): void
    {
        $this->travelTo('2026-10-26 09:00:00');
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        Livewire::test(AdminPressingsCreate::class)
            ->set('name', 'Pressing du Lancement')
            ->set('phone', '+2250701020304')
            ->call('create');

        $subscription = Subscription::where('pressing_id', Pressing::where('name', 'Pressing du Lancement')->value('id'))->firstOrFail();
        // Gratuit jusqu'au 15 novembre, puis la semaine d'essai pour choisir sa formule.
        $this->assertSame('2026-11-22 23:59:59', $subscription->trial_ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-22 23:59:59', $subscription->current_period_ends_at->format('Y-m-d H:i:s'));
    }

    public function test_pressings_created_after_the_offer_get_the_usual_trial(): void
    {
        $this->travelTo('2026-11-20 09:00:00');
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        Livewire::test(AdminPressingsCreate::class)
            ->set('name', 'Pressing Après')
            ->set('phone', '+2250701020304')
            ->call('create');

        // Durée d'essai réglée par le Super Admin (7 jours par défaut).
        $subscription = Subscription::where('pressing_id', Pressing::where('name', 'Pressing Après')->value('id'))->firstOrFail();
        $this->assertSame(now()->addDays(Subscription::trialDays())->toDateString(), $subscription->trial_ends_at->toDateString());
    }
}
