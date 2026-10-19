<?php

namespace Tests\Feature;

use App\Enums\OrderIssueCategory;
use App\Enums\OrderIssueStatus;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Enums\SubscriptionStatus;
use App\Livewire\Admin\Clients\Index as AdminClientsIndex;
use App\Livewire\Admin\Orders\Index as AdminOrdersIndex;
use App\Livewire\Admin\Pressings\Index as AdminPressingsIndex;
use App\Livewire\Admin\Pressings\Show as AdminPressingsShow;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Listes Pressings / Commandes / Clients de la console Super Admin :
 * filtres, panneaux de détail, export CSV et actions de gestion.
 */
class SuperAdminListsAndCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($this->superAdmin);
    }

    // ---------- Pressings ----------

    public function test_pressings_can_be_filtered_by_subscription_status_and_sorted_by_activity(): void
    {
        $quiet = Subscription::factory()->active()->create()->pressing;
        $quiet->update(['name' => 'Pressing Calme']);
        $busy = Subscription::factory()->active()->create()->pressing;
        $busy->update(['name' => 'Pressing Animé']);
        Order::factory()->for($busy)->count(2)->create();
        Subscription::factory()->create(['status' => SubscriptionStatus::Expired])->pressing->update(['name' => 'Pressing Expiré']);

        Livewire::test(AdminPressingsIndex::class)
            ->set('subscriptionStatus', 'active')
            ->set('sort', 'activity')
            ->assertViewHas('pressings', fn ($p) => $p->pluck('name')->all() === ['Pressing Animé', 'Pressing Calme'])
            ->set('subscriptionStatus', 'expired')
            ->assertSee('Pressing Expiré')
            ->assertDontSee('Pressing Animé');
    }

    public function test_super_admin_can_edit_pressing_information(): void
    {
        $pressing = Pressing::factory()->create(['name' => 'Ancien nom']);

        Livewire::test(AdminPressingsShow::class, ['pressing' => $pressing])
            ->call('editInfo')
            ->set('info.name', 'Nouveau nom')
            ->set('info.phone', '+2250701020304')
            ->set('info.city', 'Bouaké')
            ->call('saveInfo')
            ->assertHasNoErrors()
            ->assertSet('editingInfo', false);

        $pressing->refresh();
        $this->assertSame('Nouveau nom', $pressing->name);
        $this->assertSame('Bouaké', $pressing->city);
    }

    public function test_pressing_edit_rejects_an_invalid_phone(): void
    {
        $pressing = Pressing::factory()->create();

        Livewire::test(AdminPressingsShow::class, ['pressing' => $pressing])
            ->call('editInfo')
            ->set('info.phone', '070102030')
            ->call('saveInfo')
            ->assertHasErrors('info.phone');
    }

    public function test_deleting_a_pressing_requires_typing_its_code_and_keeps_accounts(): void
    {
        $pressing = Pressing::factory()->create();
        $manager = User::factory()->create();
        $pressing->staff()->attach($manager, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $order = Order::factory()->for($pressing)->create();

        Livewire::test(AdminPressingsShow::class, ['pressing' => $pressing])
            ->set('deleteConfirmation', 'MAUVAIS')
            ->call('deletePressing')
            ->assertHasErrors('deleteConfirmation');
        $this->assertModelExists($pressing);

        Livewire::test(AdminPressingsShow::class, ['pressing' => $pressing])
            ->set('deleteConfirmation', $pressing->code)
            ->call('deletePressing')
            ->assertRedirect(route('admin.pressings.index'));

        $this->assertModelMissing($pressing);
        $this->assertModelMissing($order);
        $this->assertModelExists($manager);
        $this->assertModelExists($order->customer);
    }

    // ---------- Commandes ----------

    public function test_orders_can_be_filtered_by_pressing_dates_late_and_open_issues(): void
    {
        $pressingA = Pressing::factory()->create();
        $pressingB = Pressing::factory()->create();
        $late = Order::factory()->for($pressingA)->create(['expected_at' => now()->subDay()]);
        $old = Order::factory()->for($pressingA)->create(['created_at' => now()->subMonths(2), 'expected_at' => now()->addDay()]);
        $withIssue = Order::factory()->for($pressingB)->withStatus(OrderStatus::Prete)->create(['expected_at' => now()->subDay()]);
        OrderIssue::create([
            'order_id' => $withIssue->id, 'customer_id' => $withIssue->customer_id,
            'category' => OrderIssueCategory::MissingItem, 'description' => 'Il manque une chemise', 'status' => OrderIssueStatus::Open,
        ]);

        $ids = fn ($orders) => $orders->pluck('id')->sort()->values()->all();

        Livewire::test(AdminOrdersIndex::class)
            ->set('pressingId', (string) $pressingA->id)
            ->assertViewHas('orders', fn ($o) => $ids($o) === [$late->id, $old->id])
            ->set('dateFrom', now()->subWeek()->toDateString())
            ->assertViewHas('orders', fn ($o) => $ids($o) === [$late->id])
            ->call('resetFilters')
            ->set('lateOnly', true) // une commande prête n'est pas « en retard »
            ->assertViewHas('orders', fn ($o) => $ids($o) === [$late->id])
            ->call('resetFilters')
            ->set('withOpenIssues', true)
            ->assertViewHas('orders', fn ($o) => $ids($o) === [$withIssue->id]);
    }

    public function test_status_counts_ignore_the_selected_status(): void
    {
        $pressing = Pressing::factory()->create();
        Order::factory()->for($pressing)->count(2)->create();
        Order::factory()->for($pressing)->withStatus(OrderStatus::Prete)->create();

        Livewire::test(AdminOrdersIndex::class)
            ->set('status', OrderStatus::Prete->value)
            ->assertViewHas('orders', fn ($o) => $o->total() === 1)
            ->assertViewHas('statusCounts', fn ($c) => (int) $c['recue'] === 2 && (int) $c['prete'] === 1);
    }

    public function test_order_detail_panel_shows_items_and_issues(): void
    {
        $order = Order::factory()->create(['total_fcfa' => 3_000]);
        $order->items()->create(['name' => 'Boubou brodé', 'unit_price_fcfa' => 3_000, 'quantity' => 1]);
        OrderIssue::create([
            'order_id' => $order->id, 'customer_id' => $order->customer_id,
            'category' => OrderIssueCategory::DamagedItem, 'description' => 'Bouton arraché', 'status' => OrderIssueStatus::Open,
        ]);

        Livewire::test(AdminOrdersIndex::class)
            ->call('showOrder', $order->id)
            ->assertSee('Boubou brodé')
            ->assertSee('Bouton arraché')
            ->call('closeOrder')
            ->assertDontSee('Bouton arraché');
    }

    // ---------- Clients ----------

    public function test_super_admin_can_create_a_client_attached_to_a_pressing(): void
    {
        $pressing = Pressing::factory()->create();

        Livewire::test(AdminClientsIndex::class)
            ->call('openCreateForm')
            ->set('form.first_name', 'Awa')
            ->set('form.last_name', 'Koné')
            ->set('form.phone', '+225 0701020304')
            ->set('form.pressing_id', (string) $pressing->id)
            ->call('saveCustomer')
            ->assertHasNoErrors()
            ->assertSet('formMode', null);

        $customer = Customer::where('phone', '+2250701020304')->firstOrFail();
        $this->assertSame('Awa', $customer->first_name);
        // Le compte attend le client, qui choisira son mot de passe en s'inscrivant.
        $this->assertFalse($customer->hasAppAccount());
        $this->assertTrue($customer->pressings->contains($pressing));
    }

    public function test_creating_a_client_with_an_existing_phone_is_rejected(): void
    {
        Customer::factory()->create(['phone' => '+2250701020304']);

        Livewire::test(AdminClientsIndex::class)
            ->call('openCreateForm')
            ->set('form.first_name', 'Awa')
            ->set('form.last_name', 'Koné')
            ->set('form.phone', '+2250701020304')
            ->call('saveCustomer')
            ->assertHasErrors('form.phone');
    }

    public function test_super_admin_can_edit_attach_detach_and_reset_a_client(): void
    {
        $customer = Customer::factory()->create(['first_name' => 'Ancien']);
        $customer->createToken('app');
        $pressing = Pressing::factory()->create();

        $component = Livewire::test(AdminClientsIndex::class)
            ->call('showCustomer', $customer->id)
            ->call('editCustomer')
            ->set('form.first_name', 'Fatou')
            ->call('saveCustomer')
            ->assertHasNoErrors()
            ->set('attachPressingId', (string) $pressing->id)
            ->call('attachPressing');

        $this->assertSame('Fatou', $customer->refresh()->first_name);
        $this->assertTrue($customer->pressings()->whereKey($pressing->id)->exists());

        $component->call('detachPressing', $pressing->id);
        $this->assertFalse($customer->pressings()->whereKey($pressing->id)->exists());

        $component->call('resetPassword')->assertDispatched('toast');
        $this->assertTrue(Hash::check('123456', $customer->refresh()->password));
        $this->assertTrue($customer->must_change_password);
        $this->assertSame(0, $customer->tokens()->count());
    }

    public function test_super_admin_can_delete_a_client_and_their_orders(): void
    {
        $order = Order::factory()->create();
        $customer = $order->customer;

        Livewire::test(AdminClientsIndex::class)
            ->call('showCustomer', $customer->id)
            ->call('deleteCustomer')
            ->assertSet('selectedCustomerId', null);

        $this->assertModelMissing($customer);
        $this->assertModelMissing($order);
    }

    public function test_clients_can_be_filtered_by_activity_and_sorted_by_amount(): void
    {
        $big = Customer::factory()->create(['first_name' => 'Gros']);
        $small = Customer::factory()->create(['first_name' => 'Petit']);
        $never = Customer::factory()->create(['first_name' => 'Jamais']);
        Order::factory()->for($big)->create(['total_fcfa' => 9_000]);
        Order::factory()->for($small)->create(['total_fcfa' => 1_000, 'created_at' => now()->subMonths(2)]);

        Livewire::test(AdminClientsIndex::class)
            ->set('sort', 'spent')
            ->assertViewHas('customers', fn ($c) => $c->pluck('first_name')->take(2)->all() === ['Gros', 'Petit'])
            ->set('activity', 'inactive')
            ->assertViewHas('customers', fn ($c) => $c->pluck('id')->all() === [$small->id])
            ->set('activity', 'never')
            ->assertViewHas('customers', fn ($c) => $c->pluck('id')->all() === [$never->id]);
    }

    public function test_search_finds_a_client_by_full_name(): void
    {
        Customer::factory()->create(['first_name' => 'Aya', 'last_name' => 'Bamba']);
        Customer::factory()->create(['first_name' => 'Koffi', 'last_name' => 'Yao']);

        Livewire::test(AdminClientsIndex::class)
            ->set('search', 'Aya Bamba')
            ->assertViewHas('customers', fn ($c) => $c->pluck('last_name')->all() === ['Bamba']);
    }

    // ---------- Accès et export ----------

    public function test_csv_exports_respect_filters(): void
    {
        $pressingA = Pressing::factory()->create(['name' => 'Pressing Alpha']);
        Pressing::factory()->create(['name' => 'Pressing Beta']);
        Order::factory()->for($pressingA)->create(['order_number' => 'PL-EXPORT-1']);

        $csv = $this->get(route('admin.export', ['type' => 'pressings', 'search' => 'Alpha']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Pressing Alpha', $csv);
        $this->assertStringNotContainsString('Pressing Beta', $csv);

        $csv = $this->get(route('admin.export', ['type' => 'orders', 'pressing' => $pressingA->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString('PL-EXPORT-1', $csv);

        $this->get(route('admin.export', ['type' => 'clients']))->assertOk();
    }

    public function test_non_super_admins_cannot_export_or_manage_clients(): void
    {
        $pressing = Pressing::factory()->create();
        $staff = User::factory()->create();
        $pressing->staff()->attach($staff, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $this->actingAs($staff);

        $this->get(route('admin.export', ['type' => 'clients']))->assertForbidden();
        Livewire::test(AdminClientsIndex::class)->assertForbidden();
    }
}
