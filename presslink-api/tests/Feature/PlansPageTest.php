<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Livewire\Admin\Pressings\Show as AdminPressingShow;
use App\Livewire\Subscription\Plans;
use App\Livewire\Subscription\Show as SubscriptionShow;
use App\Models\Pressing;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Page Formules (espace pressing), page Tarifs (site) et paiement des
 * demandes par lien Wave envoyé par l'équipe PressLink.
 */
class PlansPageTest extends TestCase
{
    use RefreshDatabase;

    private function ownerOn(SubscriptionPlan $plan, array $attributes = []): array
    {
        $pressing = Pressing::factory()->create();
        $subscription = Subscription::factory()->for($pressing)->active($plan)->create($attributes);
        $owner = User::factory()->create();
        $pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return [$pressing, $subscription, $owner];
    }

    public function test_the_public_pricing_page_shows_the_plans_with_the_prices_set_by_presslink(): void
    {
        Setting::write('plans', ['business' => ['monthly' => 35000, 'annual' => 350000]]);

        $this->get(route('site.pricing'))
            ->assertOk()
            ->assertSee('Starter')
            ->assertSee('Premium')
            ->assertSee('35 000')
            ->assertSee('2 mois offerts')
            ->assertSee('Tout Starter, plus :')
            ->assertSee('Caisse complète')
            ->assertSee('Essai gratuit de 14 jours');
    }

    public function test_the_plans_page_shows_the_current_plan_and_what_the_next_one_adds(): void
    {
        [, , $owner] = $this->ownerOn(SubscriptionPlan::Starter);

        Livewire::actingAs($owner)->test(Plans::class)
            ->assertSee('Votre formule actuelle')
            ->assertSee('En passant de Starter à Business')
            ->assertSee('5 000 commandes par mois au lieu de 1 500')
            ->assertSee('Exports Excel et PDF')
            ->assertSee('Passer à Business');
    }

    public function test_a_plan_change_request_carries_the_amount_to_pay(): void
    {
        [$pressing, , $owner] = $this->ownerOn(SubscriptionPlan::Starter);

        Livewire::actingAs($owner)->test(Plans::class)
            ->set('cycle', 'annual')
            ->call('requestPlan', 'premium', 'annual')
            ->assertSee('Demande envoyée');

        $request = SubscriptionRequest::sole();
        $this->assertSame(SubscriptionPlan::Premium, $request->plan);
        $this->assertSame('annual', $request->cycle);
        $this->assertSame(SubscriptionPlan::Premium->annualPriceFcfa(), $request->amount_fcfa);
        $this->assertSame($pressing->id, $request->pressing_id);
    }

    public function test_presslink_sends_a_wave_link_and_the_pressing_sees_pay_with_wave(): void
    {
        [$pressing, $subscription, $owner] = $this->ownerOn(SubscriptionPlan::Business, ['current_period_ends_at' => now()->addDays(3)]);

        Livewire::actingAs($owner)->test(SubscriptionShow::class)->call('requestRenewal', 'monthly');
        $request = SubscriptionRequest::sole();
        $this->assertSame(SubscriptionPlan::Business->monthlyPriceFcfa(), $request->amount_fcfa);

        $admin = User::factory()->create(['is_super_admin' => true]);
        Livewire::actingAs($admin)->test(AdminPressingShow::class, ['pressing' => $pressing])
            ->set("requestLinks.{$request->id}", 'pas-un-lien')
            ->call('savePaymentLink', $request->id)
            ->assertHasErrors("requestLinks.{$request->id}")
            ->set("requestLinks.{$request->id}", 'https://pay.example.test/presslink/50000')
            ->call('savePaymentLink', $request->id)
            ->assertHasNoErrors()
            ->assertSee('Envoyer sur WhatsApp');

        Livewire::actingAs($owner)->test(SubscriptionShow::class)
            ->assertSee('Payer avec Wave')
            ->assertSee('https://pay.example.test/presslink/50000');

        // Payé : la période repart de la fin de la période en cours.
        $previousEnd = $subscription->current_period_ends_at->copy();
        Livewire::actingAs($admin)->test(AdminPressingShow::class, ['pressing' => $pressing])->call('applyRequest', $request->id);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->current_period_ends_at->isSameDay($previousEnd->addMonthNoOverflow()));
    }

    public function test_renewing_an_expired_subscription_starts_from_today(): void
    {
        [$pressing, $subscription] = $this->ownerOn(SubscriptionPlan::Starter, ['status' => SubscriptionStatus::Expired, 'current_period_ends_at' => now()->subWeek()]);
        $request = $pressing->subscriptionRequests()->create(['kind' => SubscriptionRequest::KIND_RENEWAL, 'plan' => SubscriptionPlan::Starter, 'cycle' => 'annual', 'status' => SubscriptionRequest::PENDING]);

        $request->apply();

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('annual', $subscription->billing_cycle);
        $this->assertTrue($subscription->current_period_ends_at->isSameDay(now()->addYear()));
    }
}
