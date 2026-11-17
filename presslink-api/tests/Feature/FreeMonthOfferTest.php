<?php

namespace Tests\Feature;

use App\Livewire\Admin\Plans\Index as AdminPlans;
use App\Livewire\Admin\Pressings\Create as AdminPressingsCreate;
use App\Models\Pressing;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Support\PlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Offre : chaque nouveau pressing a un mois offert, toutes les fonctions
 * ouvertes, à partir du jour de sa création ; il choisit ensuite sa formule.
 */
class FreeMonthOfferTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_a_new_pressing_gets_one_free_month_from_the_day_it_is_created(): void
    {
        $this->travelTo('2026-11-18 09:00:00');
        $this->actingAs($this->superAdmin());

        Livewire::test(AdminPressingsCreate::class)
            ->set('name', 'Pressing du Mois Offert')
            ->set('phone', '+2250701020304')
            ->call('create');

        $subscription = Subscription::where('pressing_id', Pressing::where('name', 'Pressing du Mois Offert')->value('id'))->firstOrFail();
        $this->assertTrue($subscription->isInTrial());
        $this->assertSame('2026-12-18', $subscription->trial_ends_at->toDateString());
        $this->assertSame('2026-12-18', $subscription->current_period_ends_at->toDateString());
    }

    public function test_the_site_announces_the_free_month_and_no_launch_date(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('1 mois offert à chaque nouveau pressing')
            ->assertSee('Inscrire mon pressing gratuitement')
            ->assertSee('id="rejoindre"', false)
            ->assertDontSee('Lancement officiel')
            ->assertDontSee('Gratuit jusqu');

        $this->get(route('site.clients'))->assertOk()->assertSee('Nouveau pressing : 1 mois offert');
        $this->get(route('signup'))->assertOk()->assertSee('1 mois offert');
    }

    public function test_the_super_admin_can_still_change_the_length_of_the_trial(): void
    {
        $this->assertSame(30, Subscription::trialDays());
        $this->assertSame('1 mois offert', PlanCatalog::trialOffer());

        Livewire::actingAs($this->superAdmin())->test(AdminPlans::class)
            ->assertSet('trialDays', '30')
            ->set('trialDays', '14')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(14, Setting::read('trial_days'));
        $this->assertSame('14 jours offerts', PlanCatalog::trialOffer());
        $this->get(route('site.pricing'))->assertOk()->assertSee('Essayer gratuitement 14 jours');
    }
}
