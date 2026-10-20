<?php

namespace Tests\Feature;

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\CreateOrderAction;
use App\Enums\ActivityAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PressingRole;
use App\Livewire\Orders\Show as OrdersShow;
use App\Livewire\Pressing\Settings as PressingSettings;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Conditions et règlement du pressing, et droit de magasinage des commandes
 * prêtes non retirées : calcul, ajout à la remise, exonération tracée, et
 * affichage côté client (application, reçu).
 */
class StorageFeeAndTermsTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 10:00');
        $this->pressing = Pressing::factory()->create([
            'terms' => "Les vêtements sont rendus sur présentation du reçu.\nRéclamations au moment du retrait.",
            'storage_fee' => ['enabled' => true, 'grace_days' => 7, 'amount' => 200, 'unit' => 'order', 'period' => 'day', 'cap' => null],
        ]);
        $this->customer = Customer::factory()->create();
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staff(string $roleKey = Role::OWNER): User
    {
        $role = $this->pressing->defaultRole($roleKey);
        $user = User::factory()->create();
        $this->pressing->staff()->attach($user, ['role' => $role->isOwner() ? PressingRole::Admin->value : PressingRole::Employee->value, 'role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($user);

        return $user;
    }

    /** Commande de 3 pièces, retrait prévu le 12/10, prête le 11/10. */
    private function readyOrder(int $paid = 0): Order
    {
        $order = (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 3],
        ], expectedAt: '2026-10-12', amountPaidFcfa: $paid);

        Carbon::setTestNow('2026-10-11 09:00');
        $order->update(['status' => OrderStatus::Traitement]);
        $order->update(['status' => OrderStatus::Prete]);

        return $order->fresh();
    }

    public function test_the_fee_starts_after_the_grace_period_and_grows_each_day(): void
    {
        $this->staff();
        $order = $this->readyOrder();

        Carbon::setTestNow('2026-10-19 18:00'); // dernier jour gratuit (12 + 7)
        $this->assertNull($order->storageFee());

        Carbon::setTestNow('2026-10-22 08:00'); // 3e jour facturé
        $this->assertSame(['days' => 3, 'amount' => 600], array_slice($order->storageFee(), 0, 2));
    }

    public function test_per_piece_weekly_and_capped(): void
    {
        $this->staff();
        $this->pressing->update(['storage_fee' => ['enabled' => true, 'grace_days' => 0, 'amount' => 100, 'unit' => 'piece', 'period' => 'week', 'cap' => 500]]);
        $order = $this->readyOrder();

        Carbon::setTestNow('2026-10-14 10:00'); // 2 jours → 1 semaine × 100 F × 3 pièces
        $this->assertSame(300, $order->fresh()->storageFee()['amount']);

        Carbon::setTestNow('2026-10-25 10:00'); // 13 jours → 2 semaines → 600 F, plafonné à 500 F
        $this->assertSame(500, $order->fresh()->storageFee()['amount']);
    }

    public function test_a_late_ready_order_is_not_charged_for_the_pressing_delay(): void
    {
        $this->staff();
        $order = (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => null, 'name' => 'Robe', 'unit_price_fcfa' => 2000, 'quantity' => 1],
        ], expectedAt: '2026-10-12');
        $order->update(['status' => OrderStatus::Traitement]);

        Carbon::setTestNow('2026-10-25 10:00');
        $this->assertNull($order->fresh()->storageFee()); // pas encore prête

        $order->update(['status' => OrderStatus::Prete]); // prête le 25 : le délai part de là
        Carbon::setTestNow('2026-11-01 10:00'); // 7e jour gratuit
        $this->assertNull($order->fresh()->storageFee());
        Carbon::setTestNow('2026-11-02 10:00');
        $this->assertSame(200, $order->fresh()->storageFee()['amount']);
    }

    public function test_the_fee_is_added_at_hand_over_and_traced(): void
    {
        $this->staff();
        $order = $this->readyOrder(paid: 3000);
        Carbon::setTestNow('2026-10-21 10:00'); // 2 jours facturés : 400 F

        Livewire::test(OrdersShow::class, ['order' => $order])
            ->assertSee('Droit de magasinage : 400 F')
            ->assertSee('Encaisser 400 F et remettre')
            ->call('transitionTo', OrderStatus::Recuperee->value)
            ->assertSet('errorMessage', 'Encaissez le reste à payer (400 F) avant de remettre la commande.');
        $this->assertFalse($order->fresh()->hasStorageFeeLine());

        Livewire::test(OrdersShow::class, ['order' => $order->fresh()])->set('paymentMethod', PaymentMethod::Wave->value)->call('settleAndRecover');

        $order->refresh();
        $this->assertSame(OrderStatus::Recuperee, $order->status);
        $this->assertSame(3400, $order->total_fcfa);
        $this->assertSame(0, $order->balance_due_fcfa);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'name' => 'Droit de magasinage (2 jours de retard)', 'unit_price_fcfa' => 400]);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'amount_fcfa' => 400, 'method' => 'wave']);
        $this->assertStringContainsString('Droit de magasinage de 400 F ajouté', ActivityLog::where('action', ActivityAction::StorageFeeCharged)->firstOrFail()->description);
    }

    public function test_only_allowed_roles_waive_the_fee_and_it_is_logged(): void
    {
        $order = $this->readyOrder(paid: 3000);
        Carbon::setTestNow('2026-10-21 10:00');

        $this->staff(Role::EMPLOYEE);
        $this->expectExceptionMessage('exonérer');
        (new ChangeOrderStatusAction)->handle($order, OrderStatus::Recuperee, PaymentMethod::Cash, waiveStorageFee: true);
    }

    public function test_the_owner_waives_the_fee(): void
    {
        $order = $this->readyOrder(paid: 3000);
        Carbon::setTestNow('2026-10-21 10:00');
        $this->staff(Role::OWNER);

        Livewire::test(OrdersShow::class, ['order' => $order])
            ->set('waiveStorageFee', true)
            ->assertSee('offert au client')
            ->call('settleAndRecover');

        $order->refresh();
        $this->assertSame(OrderStatus::Recuperee, $order->status);
        $this->assertSame(3000, $order->total_fcfa);
        $this->assertTrue(ActivityLog::where('action', ActivityAction::StorageFeeWaived)->exists());
    }

    public function test_the_owner_configures_terms_and_fee_in_settings(): void
    {
        $this->staff();
        $this->pressing->update(['storage_fee' => null, 'terms' => null]);

        Livewire::test(PressingSettings::class)
            ->set('terms', "Règle 1\nRègle 2")
            ->set('storageFeeEnabled', true)
            ->set('storageFeeAmount', '')
            ->call('save')
            ->assertHasErrors(['storageFeeAmount'])
            ->set('storageFeeAmount', '150')
            ->set('storageFeeGraceDays', '10')
            ->set('storageFeePeriod', 'day')
            ->set('storageFeeUnit', 'piece')
            ->assertSee('au-delà de 10 jours après la date de retrait prévue, 150 F par jour et par pièce')
            ->call('save')
            ->assertHasNoErrors();

        $pressing = $this->pressing->fresh();
        $this->assertSame("Règle 1\nRègle 2", $pressing->terms);
        $this->assertSame(['grace_days' => 10, 'amount' => 150, 'unit' => 'piece', 'period' => 'day', 'cap' => null], $pressing->storageFeePolicy());
    }

    public function test_customers_see_terms_and_fee_in_the_app_and_on_the_receipt(): void
    {
        $owner = $this->staff();
        $order = $this->readyOrder();
        Carbon::setTestNow('2026-10-21 10:00');

        $token = $this->customer->createToken('app')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/pressings/mine')
            ->assertOk()
            ->assertJsonPath('0.terms', $this->pressing->terms)
            ->assertJsonPath('0.storage_fee_clause', $this->pressing->storageFeeClause());

        $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('storage_fee.amount', 400)
            ->assertJsonPath('storage_fee.days', 2);

        $this->actingAs($owner)->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee('Les vêtements sont rendus sur présentation du reçu.')
            ->assertSee('Droit de magasinage : au-delà de 7 jours');
    }
}
