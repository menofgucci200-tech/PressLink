<?php

namespace Tests\Feature;

use App\Enums\Feature;
use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Livewire\Pressing\Delivery;
use App\Livewire\Subscription\Show as SubscriptionShow;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ramassage et livraison, lot 1 : la fonction de formule (Business et
 * au-dessus, ou en option), les réglages du pressing et ce que l'appli
 * client en reçoit. Tant que le service n'est pas lancé, il ne paraît ni
 * sur le site ni à l'abonnement.
 */
class PickupDeliverySettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le service est lancé par défaut ; ces tests partent de l'état masqué
     * (DELIVERY_LAUNCHED=false, l'arrêt d'urgence) et le lancent eux-mêmes.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['presslink.delivery.launched' => false]);
    }

    private function pressingOn(SubscriptionPlan $plan): Pressing
    {
        $pressing = Pressing::factory()->create();
        Subscription::factory()->for($pressing)->active($plan)->create();

        return $pressing;
    }

    private function ownerOf(Pressing $pressing): User
    {
        $owner = User::factory()->create();
        $pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return $owner;
    }

    private function launch(): void
    {
        config(['presslink.delivery.launched' => true]);
    }

    public function test_business_and_premium_include_pickup_and_delivery_but_not_starter(): void
    {
        $this->assertFalse(SubscriptionPlan::Starter->includes(Feature::Delivery));
        $this->assertTrue(SubscriptionPlan::Business->includes(Feature::Delivery));
        $this->assertTrue(SubscriptionPlan::Premium->includes(Feature::Delivery));
        $this->assertSame(SubscriptionPlan::Business, Feature::Delivery->includedFrom());
    }

    public function test_before_launch_the_service_is_neither_advertised_nor_sold(): void
    {
        $this->get(route('site.pricing'))->assertOk()->assertDontSee('Ramassage et livraison');
        $this->get(route('site.pressings'))->assertOk()->assertDontSee('Ramassage et livraison');

        $pressing = $this->pressingOn(SubscriptionPlan::Starter);
        $this->actingAs($this->ownerOf($pressing));
        Livewire::test(SubscriptionShow::class)
            ->assertDontSee('Ramassage et livraison')
            ->call('requestOption', Feature::Delivery->value)
            ->assertStatus(422);
    }

    public function test_a_starter_pressing_buys_the_service_by_moving_to_business_without_a_manual_request(): void
    {
        $this->launch();
        config([
            'services.geniuspay.base_url' => 'https://geniuspay.test/api/v1/merchant',
            'services.geniuspay.api_key' => 'pk_sandbox_test',
            'services.geniuspay.api_secret' => 'sk_sandbox_test',
        ]);
        $pressing = $this->pressingOn(SubscriptionPlan::Starter);
        $this->actingAs($this->ownerOf($pressing));

        $component = Livewire::test(SubscriptionShow::class)
            ->assertSee('Passer à Business')
            ->call('requestOption', Feature::Delivery->value);

        $request = $pressing->subscriptionRequests()->sole();
        $this->assertSame(SubscriptionRequest::KIND_PLAN, $request->kind);
        $this->assertSame(SubscriptionPlan::Business, $request->plan);
        $component->assertRedirect(route('subscription.checkout', $request));
    }

    public function test_once_launched_the_service_shows_on_the_pricing_page(): void
    {
        $this->launch();

        $this->get(route('site.pricing'))->assertOk()->assertSee('Ramassage et livraison');
    }

    public function test_a_business_owner_prepares_the_service_before_launch(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Business);
        $this->actingAs($this->ownerOf($pressing));

        $this->get(route('pressing.settings'))->assertOk()->assertSee(route('pressing.delivery'), false);
        $this->get(route('pressing.delivery'))->assertOk()->assertSee('Pas encore visible par vos clients');

        Livewire::test(Delivery::class)
            ->set('pickupEnabled', true)
            ->set('deliveryEnabled', true)
            ->set('communes', ['Cocody', 'Bingerville'])
            ->set('pickupFee', '1000')
            ->set('deliveryFee', '500')
            ->set('freeFrom', '15000')
            ->set('timeSlots', [['from' => '14:00', 'to' => '18:00'], ['from' => '08:00', 'to' => '12:00']])
            ->set('turnaroundHours', '48')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('timeSlots', [['from' => '08:00', 'to' => '12:00'], ['from' => '14:00', 'to' => '18:00']]);

        $pressing->refresh();
        $this->assertTrue($pressing->pickup_enabled);
        $this->assertTrue($pressing->delivery_enabled);
        $this->assertSame(1000, $pressing->pickup_fee_fcfa);
        $this->assertSame(500, $pressing->delivery_fee_fcfa);
        $this->assertSame(15000, $pressing->delivery_free_from_fcfa);
        $this->assertSame(48, $pressing->turnaround_hours);
        $this->assertSame(['Bingerville', 'Cocody'], $pressing->serviceAreas()->orderBy('commune')->pluck('commune')->all());

        // Une commune décochée n'est plus desservie.
        Livewire::test(Delivery::class)->set('communes', ['Cocody'])->call('save')->assertHasNoErrors();
        $this->assertSame(['Cocody'], $pressing->serviceAreas()->pluck('commune')->all());
    }

    public function test_settings_are_checked_before_saving(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Premium);
        $this->actingAs($this->ownerOf($pressing));

        Livewire::test(Delivery::class)
            ->set('pickupEnabled', true)
            ->set('communes', [])
            ->call('save')
            ->assertHasErrors(['communes' => 'required']);

        Livewire::test(Delivery::class)
            ->set('communes', ['Atlantis'])
            ->set('timeSlots', [['from' => '12:00', 'to' => '08:00']])
            ->set('pickupFee', '-5')
            ->call('save')
            ->assertHasErrors(['communes.0', 'timeSlots.0.to', 'pickupFee']);

        $this->assertSame(0, $pressing->serviceAreas()->count());
    }

    public function test_a_starter_pressing_is_refused_before_launch_and_sent_to_the_option_after(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Starter);
        $owner = $this->ownerOf($pressing);

        $this->actingAs($owner)->get(route('pressing.settings'))->assertOk()->assertDontSee(route('pressing.delivery'), false);
        $this->actingAs($owner)->get(route('pressing.delivery'))->assertForbidden();

        $this->launch();
        $this->actingAs($owner)->get(route('pressing.delivery'))
            ->assertRedirect(route('subscription.show', ['option' => Feature::Delivery->value]));
    }

    public function test_a_starter_pressing_with_the_option_can_set_it_up(): void
    {
        $pressing = Pressing::factory()->create();
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Starter)->create(['addons' => [Feature::Delivery->value]]);

        $this->actingAs($this->ownerOf($pressing))->get(route('pressing.delivery'))->assertOk();
    }

    public function test_the_customer_app_receives_the_services_only_once_launched_and_set_up(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Business);
        $pressing->update(['pickup_enabled' => true, 'delivery_enabled' => false, 'pickup_fee_fcfa' => 1000]);
        $pressing->serviceAreas()->create(['commune' => 'Cocody']);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $token = $customer->createToken('test')->plainTextToken;
        $mine = fn () => $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/pressings/mine')->assertOk();

        $mine()->assertJsonPath('0.delivery_services', null)->assertJsonMissingPath('0.pickup_enabled');

        $this->launch();
        $mine()
            ->assertJsonPath('0.delivery_services.pickup', true)
            ->assertJsonPath('0.delivery_services.delivery', false)
            ->assertJsonPath('0.delivery_services.pickup_fee_fcfa', 1000)
            ->assertJsonPath('0.delivery_services.communes', ['Cocody'])
            ->assertJsonPath('0.delivery_services.slots', Pressing::DEFAULT_DELIVERY_SLOTS);

        // Sans commune desservie, le service n'est pas proposé.
        $pressing->serviceAreas()->delete();
        $mine()->assertJsonPath('0.delivery_services', null);
    }
}
