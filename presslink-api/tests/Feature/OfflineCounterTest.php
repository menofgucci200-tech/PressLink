<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\OfflineOperation;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Comptoir hors connexion : la copie des données gardée sur le PC et
 * l'envoi des opérations faites sans Internet (ApplyOfflineOperations).
 */
class OfflineCounterTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private User $owner;

    private Service $chemise;

    private int $nettoyage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create();
        Subscription::factory()->for($this->pressing)->active(SubscriptionPlan::Business)->create();
        $this->owner = User::factory()->create();
        $this->pressing->staff()->attach($this->owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $this->chemise = Service::factory()->for($this->pressing)->create(['name' => 'Chemise', 'price_fcfa' => 0]);
        $this->nettoyage = $this->pressing->cleaningPrestation()->id;
        $this->chemise->setPrice($this->nettoyage, 1000);
    }

    private function customer(): Customer
    {
        $customer = Customer::factory()->create();
        $this->pressing->customers()->attach($customer, ['joined_at' => now()]);

        return $customer;
    }

    /** @param  list<array<string, mixed>>  $operations */
    private function send(array $operations, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->postJson(route('offline.sync'), [
            'pressing_id' => $this->pressing->id,
            'operations' => $operations,
        ]);
    }

    private function op(string $type, array $data, ?string $at = null): array
    {
        return ['uuid' => (string) Str::uuid(), 'type' => $type, 'at' => $at ?? now()->subHour()->toIso8601String(), ...$data];
    }

    public function test_the_snapshot_holds_catalogue_prices_customers_and_open_orders(): void
    {
        $customer = $this->customer();
        Order::factory()->for($this->pressing)->for($customer)->create(['status' => OrderStatus::Traitement]);

        $response = $this->actingAs($this->owner)->getJson(route('offline.snapshot'))
            ->assertOk()
            ->assertJsonPath('pressing.id', $this->pressing->id)
            ->assertJsonPath('user.can.create', true)
            ->assertJsonFragment(['name' => 'Chemise'])
            ->assertJsonFragment(['id' => $customer->id, 'name' => $customer->fullName(), 'phone' => $customer->phone])
            ->assertJsonPath('orders.0.status', 'traitement')
            ->assertJsonStructure(['csrf', 'generated_at', 'payment_methods']);

        $this->assertContains([$this->chemise->id, null, $this->nettoyage, 1000], $response->json('prices'));
    }

    public function test_the_snapshot_holds_each_customer_last_order_for_repeating_it(): void
    {
        $customer = $this->customer();
        Order::factory()->for($this->pressing)->for($customer)->create();
        $last = Order::factory()->for($this->pressing)->for($customer)->create();
        $last->items()->create(['service_id' => $this->chemise->id, 'prestation_id' => $this->nettoyage, 'prestation_name' => 'Nettoyage', 'name' => 'Chemise · Nettoyage · Bleu', 'color' => 'Bleu', 'unit_price_fcfa' => 1000, 'quantity' => 4]);

        $this->actingAs($this->owner)->getJson(route('offline.snapshot'))
            ->assertOk()
            ->assertJsonPath("last_orders.{$customer->id}", [[$this->chemise->id, null, $this->nettoyage, 'Nettoyage', 'Chemise · Nettoyage · Bleu', 'Bleu', 1000, 4]])
            ->assertJsonPath('pieces.0.icon', 'chemise');
    }

    public function test_starter_has_no_offline_counter_but_trial_and_business_do(): void
    {
        $this->pressing->subscription->update(['plan' => SubscriptionPlan::Starter]);
        $this->actingAs($this->owner)->getJson(route('offline.snapshot'))->assertForbidden();
        $this->get(route('offline.show'))->assertRedirect(route('subscription.show', ['option' => 'hors_ligne']));

        $this->pressing->subscription->update(['status' => SubscriptionStatus::Trialing, 'trial_ends_at' => now()->addWeek()]);
        $this->owner->forgetRoles();
        $this->actingAs($this->owner)->getJson(route('offline.snapshot'))->assertOk();
    }

    public function test_the_counter_page_is_served_with_its_scripts(): void
    {
        $this->withoutVite();

        $this->actingAs($this->owner)->get(route('offline.show'))
            ->assertOk()
            ->assertSee('comptoir('.$this->pressing->id.', '.$this->owner->id.',', false)
            ->assertSee('Refaire sa dernière commande');
    }

    public function test_a_day_offline_is_replayed_in_order_and_only_once(): void
    {
        $at = now()->subHours(3)->startOfMinute();
        $client = $this->op('client', ['first_name' => 'Awa', 'last_name' => 'Koné', 'phone' => '07 59 12 34 56'], $at->toIso8601String());
        $order = $this->op('commande', [
            'number' => 'PL-424242',
            'customer_uuid' => $client['uuid'],
            'items' => [['service_id' => $this->chemise->id, 'prestation_id' => $this->nettoyage, 'prestation_name' => 'Nettoyage', 'name' => 'Chemise · Nettoyage', 'unit_price_fcfa' => 1000, 'quantity' => 3]],
            'expected_at' => now()->addDays(2)->format('Y-m-d'),
            'paid' => 1000,
            'method' => 'wave',
        ], $at->toIso8601String());
        $payment = $this->op('paiement', ['order_uuid' => $order['uuid'], 'amount' => 500, 'method' => 'especes']);
        $working = $this->op('statut', ['order_uuid' => $order['uuid'], 'status' => 'traitement']);
        $ready = $this->op('statut', ['order_uuid' => $order['uuid'], 'status' => 'prete']);
        $handover = $this->op('statut', ['order_uuid' => $order['uuid'], 'status' => 'recuperee', 'method' => 'orange_money']);
        $all = [$client, $order, $payment, $working, $ready, $handover];

        $first = $this->send($all)->assertOk()->json('results');
        $this->assertSame([true, true, true, true, true, true], array_column($first, 'ok'));

        $created = Order::where('order_number', 'PL-424242')->sole();
        $this->assertSame('+2250759123456', $created->customer->phone);
        $this->assertSame(OrderStatus::Recuperee, $created->status);
        $this->assertSame(3000, (int) $created->amount_paid_fcfa);
        $this->assertSame([1000, 500, 1500], $created->payments->pluck('amount_fcfa')->map(fn ($a) => (int) $a)->all());
        $this->assertTrue($created->dropped_off_at->equalTo($at));
        $this->assertSame($this->owner->id, $created->created_by);

        // Renvoyé (coupure pendant la réponse) : rien n'est appliqué deux fois.
        $again = $this->send($all)->assertOk()->json('results');
        $this->assertSame($first, $again);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, Customer::where('phone', '+2250759123456')->count());
        $this->assertSame(3, $created->payments()->count());
    }

    public function test_a_taken_number_is_replaced_and_reported(): void
    {
        $customer = $this->customer();
        Order::factory()->for($this->pressing)->for($customer)->create(['order_number' => 'PL-111111']);

        $result = $this->send([$this->op('commande', [
            'number' => 'PL-111111',
            'customer_id' => $customer->id,
            'items' => [['name' => 'Tapis', 'unit_price_fcfa' => 5000, 'quantity' => 1]],
        ])])->assertOk()->json('results.0');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['renumbered']);
        $this->assertNotSame('PL-111111', $result['order_number']);
    }

    public function test_an_order_made_offline_is_kept_even_when_closed_or_over_quota(): void
    {
        $customer = $this->customer();
        $this->pressing->update(['opening_hours' => ['dimanche-inexistant' => ['closed' => true]]]);
        $this->pressing->subscription->update(['orders_limit' => 1, 'orders_used' => 1]);

        $this->send([$this->op('commande', [
            'number' => 'PL-222222',
            'customer_id' => $customer->id,
            'items' => [['name' => 'Robe', 'unit_price_fcfa' => 2000, 'quantity' => 1]],
        ])])->assertOk()->assertJsonPath('results.0.ok', true);

        $this->assertDatabaseHas('orders', ['order_number' => 'PL-222222']);
    }

    public function test_a_known_phone_number_is_merged_into_one_customer(): void
    {
        $known = Customer::factory()->create(['phone' => '+2250707070707']);

        $result = $this->send([$this->op('client', ['first_name' => 'Ama', 'last_name' => 'Yao', 'phone' => '0707070707'])])->json('results.0');

        $this->assertTrue($result['merged']);
        $this->assertSame($known->id, $result['customer_id']);
        $this->assertTrue($this->pressing->customers()->whereKey($known->id)->exists());
    }

    public function test_payments_never_exceed_what_is_due_and_cancelled_orders_are_refused(): void
    {
        $customer = $this->customer();
        $order = Order::factory()->for($this->pressing)->for($customer)->create(['total_fcfa' => 3000, 'amount_paid_fcfa' => 0]);
        $order->items()->create(['name' => 'Costume', 'unit_price_fcfa' => 3000, 'quantity' => 1]);
        $order->recalculateTotal();

        $capped = $this->send([$this->op('paiement', ['order_id' => $order->id, 'amount' => 5000, 'method' => 'especes'])])->json('results.0');
        $this->assertTrue($capped['ok']);
        $this->assertSame(3000, $capped['amount']);

        $cancelled = Order::factory()->for($this->pressing)->for($customer)->create(['status' => OrderStatus::Annulee]);
        $refused = $this->send([$this->op('paiement', ['order_id' => $cancelled->id, 'amount' => 100, 'method' => 'especes'])])->json('results.0');
        $this->assertFalse($refused['ok']);
        $this->assertStringContainsString('annulée', $refused['message']);
        $this->assertTrue(OfflineOperation::where('uuid', $refused['uuid'])->exists());
    }

    public function test_an_order_of_another_pressing_cannot_be_touched(): void
    {
        $other = Order::factory()->create();

        $result = $this->send([$this->op('statut', ['order_id' => $other->id, 'status' => 'traitement'])])->json('results.0');

        $this->assertFalse($result['ok']);
        $this->assertSame(OrderStatus::Recue, $other->fresh()->status);
    }

    public function test_only_members_of_the_pressing_can_send(): void
    {
        $stranger = User::factory()->create();

        $this->send([], $stranger)->assertForbidden();
    }
}
