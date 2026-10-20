<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\ActivityAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Livewire\Cash\Index as CashIndex;
use App\Livewire\CommandPalette;
use App\Livewire\Dashboard;
use App\Livewire\Group\Activity;
use App\Livewire\Group\Cash;
use App\Livewire\Group\Clients;
use App\Livewire\Group\Harmonize;
use App\Livewire\Group\Orders;
use App\Livewire\Group\Reports;
use App\Livewire\Group\Team;
use App\Mail\GroupMonthlyReport;
use App\Models\CashClosing;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\Prestation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class PressingGroupTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pressing $north;

    private Pressing $south;

    private Pressing $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'owner@example.test']);
        $this->north = Pressing::factory()->create(['name' => 'Pressing Nord']);
        $this->south = Pressing::factory()->create(['name' => 'Pressing Sud']);
        $this->foreign = Pressing::factory()->create(['name' => 'Pressing Concurrent']);
        $this->attach($this->owner, $this->north, PressingRole::Admin);
        $this->attach($this->owner, $this->south, PressingRole::Admin);
    }

    private function attach(User $user, Pressing $pressing, PressingRole $role): void
    {
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);
    }

    private function order(Pressing $pressing, int $total = 5000, int $paid = 0, string $lastName = 'Koné', ?Customer $customer = null): Order
    {
        $customer ??= Customer::factory()->create(['last_name' => $lastName]);
        $pressing->customers()->syncWithoutDetaching([$customer->id => ['joined_at' => now()]]);

        return (new CreateOrderAction)->handle(
            $pressing, $customer,
            [['service_id' => null, 'service_variant_id' => null, 'name' => 'Costume', 'color' => null, 'unit_price_fcfa' => $total, 'quantity' => 1]],
            amountPaidFcfa: $paid,
        );
    }

    public function test_group_dashboard_compares_pressings_and_raises_alerts(): void
    {
        $this->order($this->north, 10000, 10000);
        $late = $this->order($this->south, 4000, 1000);
        $late->update(['expected_at' => now()->subDays(2)]);
        $this->order($this->foreign, 99000, 99000);

        Livewire::actingAs($this->owner)
            ->test(Dashboard::class)
            ->assertViewHas('totals', fn ($t) => $t['revenue'] === 11000 && $t['outstanding'] === 3000 && $t['late'] === 1)
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 2 && $rows->firstWhere('pressing.id', $this->north->id)['revenue'] === 10000)
            ->assertViewHas('alerts', fn ($alerts) => collect($alerts)->contains(fn ($a) => $a['pressing']->is($this->south) && str_contains($a['title'], 'en retard')))
            ->assertSee('Comparatif des pressings')
            ->assertDontSee('Pressing Concurrent');
    }

    public function test_group_pages_are_reserved_to_people_managing_several_pressings(): void
    {
        $singleAdmin = User::factory()->create();
        $this->attach($singleAdmin, $this->north, PressingRole::Admin);
        $employee = User::factory()->create();
        $this->attach($employee, $this->north, PressingRole::Employee);
        $this->attach($employee, $this->south, PressingRole::Employee);

        foreach ([$singleAdmin, $employee] as $user) {
            Livewire::actingAs($user)->test(Orders::class)->assertForbidden();
            $this->actingAs($user)->get(route('group.report'))->assertForbidden();
        }
    }

    public function test_a_zone_manager_only_sees_the_pressings_they_manage(): void
    {
        $third = Pressing::factory()->create(['name' => 'Pressing Est']);
        $this->attach($this->owner, $third, PressingRole::Employee);
        $inThird = $this->order($third);
        $inNorth = $this->order($this->north);

        Livewire::actingAs($this->owner)->test(Orders::class)
            ->assertSee($inNorth->order_number)
            ->assertDontSee($inThird->order_number);
    }

    public function test_group_orders_list_filters_by_pressing_and_opens_the_order_in_its_pressing(): void
    {
        $north = $this->order($this->north);
        $south = $this->order($this->south);
        $foreign = $this->order($this->foreign);

        Livewire::actingAs($this->owner)->test(Orders::class)
            ->assertSee($north->order_number)->assertSee($south->order_number)->assertDontSee($foreign->order_number)
            ->set('pressingId', (string) $this->south->id)
            ->assertDontSee($north->order_number)->assertSee($south->order_number);

        $this->actingAs($this->owner)->get(route('group.open-order', $south))
            ->assertRedirect(route('orders.show', $south))
            ->assertSessionHas('active_pressing_id', $this->south->id);
        $this->actingAs($this->owner)->get(route('group.open-order', $foreign))->assertForbidden();
    }

    public function test_switching_pressing_only_redirects_to_internal_pages(): void
    {
        $this->actingAs($this->owner)
            ->get(route('pressings.switch', ['pressing' => $this->north, 'vers' => '/commandes?status=prete']))
            ->assertRedirect('/commandes?status=prete');

        $this->actingAs($this->owner)
            ->get(route('pressings.switch', ['pressing' => $this->north, 'vers' => '//evil.example']))
            ->assertRedirect(route('dashboard'));
    }

    public function test_cash_closing_records_the_difference_and_shows_in_the_group(): void
    {
        $this->order($this->north, 6000, 6000);
        session(['active_pressing_id' => $this->north->id]);

        Livewire::actingAs($this->owner)->test(CashIndex::class)
            ->assertSee('6 000 F')
            ->set('declaredCash', '5500')
            ->set('note', 'Monnaie rendue')
            ->call('close')
            ->assertHasNoErrors();

        $closing = CashClosing::firstOrFail();
        $this->assertSame(6000, $closing->expected_cash_fcfa);
        $this->assertSame(-500, $closing->difference_fcfa);
        $this->assertDatabaseHas('activity_logs', ['pressing_id' => $this->north->id, 'action' => ActivityAction::CashClosed->value]);

        Livewire::actingAs($this->owner)->test(Cash::class)
            ->assertSee('Pressing Nord')
            ->assertSee('-500 F');
    }

    public function test_sensitive_actions_are_logged_for_the_owner(): void
    {
        $cancelled = $this->order($this->north, 5000, 2000);
        $cancelled->update(['status' => OrderStatus::Annulee]);
        $unpaid = $this->order($this->south, 3000, 1000);
        foreach ([OrderStatus::Traitement, OrderStatus::Prete, OrderStatus::Recuperee] as $status) {
            $unpaid->update(['status' => $status]);
        }

        $this->assertDatabaseHas('activity_logs', ['action' => ActivityAction::OrderCancelled->value, 'order_id' => $cancelled->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => ActivityAction::RecoveredUnpaid->value, 'order_id' => $unpaid->id]);

        Livewire::actingAs($this->owner)->test(Activity::class)
            ->set('sensitiveOnly', true)
            ->assertSee('Commande annulée')
            ->assertSee('2 000 F restant dus')
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 2
                && $logs->every(fn ($log) => $log->action->isSensitive()));
    }

    public function test_the_owner_assigns_staff_across_pressings_but_not_outside_the_group(): void
    {
        $employee = User::factory()->create(['name' => 'Awa Employée']);
        $this->attach($employee, $this->north, PressingRole::Employee);

        Livewire::actingAs($this->owner)->test(Team::class)
            ->assertSee('Awa Employée')
            ->call('assign', $employee->id, $this->south->id, PressingRole::Employee->value);
        $this->assertTrue($employee->fresh()->belongsToPressing($this->south));

        Livewire::actingAs($this->owner)->test(Team::class)
            ->call('assign', $employee->id, $this->north->id, Team::NONE);
        $this->assertFalse($employee->fresh()->belongsToPressing($this->north));

        Livewire::actingAs($this->owner)->test(Team::class)
            ->call('assign', $employee->id, $this->foreign->id, PressingRole::Admin->value)
            ->assertForbidden();

        Livewire::actingAs($this->owner)->test(Team::class)
            ->call('assign', $this->owner->id, $this->north->id, Team::NONE);
        $this->assertTrue($this->owner->fresh()->isAdminOf($this->north));
    }

    public function test_harmonizing_copies_missing_services_and_aligns_prices(): void
    {
        $shirt = Service::factory()->for($this->north)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);
        $longSleeve = $shirt->variants()->create(['name' => 'Manche longue', 'price_fcfa' => 1200, 'is_active' => true]);
        $shirt->setPrice($this->north->cleaningPrestation(), 1200, $longSleeve);
        Service::factory()->for($this->north)->create(['name' => 'Costume', 'price_fcfa' => 5000]);
        Service::factory()->for($this->south)->create(['name' => 'chemise', 'price_fcfa' => 800]);
        Service::factory()->for($this->south)->create(['name' => 'Rideau', 'price_fcfa' => 7000]);

        Livewire::actingAs($this->owner)->test(Harmonize::class)
            ->set('catalogSource', (string) $this->north->id)
            ->set('catalogTargets', [(string) $this->south->id])
            ->call('syncCatalog');

        $south = $this->south->services()->with('variants')->get()->keyBy(fn ($s) => mb_strtolower($s->name));
        $cleaning = $this->south->cleaningPrestation();
        $this->assertSame(1000, $south['chemise']->priceFor($cleaning));
        $this->assertSame(1200, $south['chemise']->priceFor($cleaning, $south['chemise']->variants->firstWhere('name', 'Manche longue')));
        $this->assertSame(5000, $south['costume']->priceFor($cleaning));
        $this->assertSame(7000, $south['rideau']->priceFor($cleaning), 'Les pièces en plus de la cible ne sont pas touchées.');
        $this->assertDatabaseHas('activity_logs', ['pressing_id' => $this->south->id, 'action' => ActivityAction::CatalogSynced->value]);

        Livewire::actingAs($this->owner)->test(Harmonize::class)
            ->set('catalogSource', (string) $this->north->id)
            ->set('catalogTargets', [(string) $this->foreign->id])
            ->call('syncCatalog')
            ->assertForbidden();
    }

    public function test_harmonizing_copies_services_and_the_price_of_each_piece_per_service(): void
    {
        $retouches = Prestation::factory()->for($this->north)->create(['name' => 'Retouches', 'icon' => 'retouche']);
        $repassage = $this->north->prestations()->where('name', 'Repassage')->firstOrFail();
        $pants = Service::factory()->for($this->north)->create(['name' => 'Pantalon', 'price_fcfa' => 1500]);
        $pants->setPrice($repassage, 700);
        $pants->setPrice($retouches, 1200);

        Livewire::actingAs($this->owner)->test(Harmonize::class)
            ->set('catalogSource', (string) $this->north->id)
            ->set('catalogTargets', [(string) $this->south->id])
            ->call('syncCatalog');

        $southRetouches = $this->south->prestations()->where('name', 'Retouches')->firstOrFail();
        $this->assertSame('retouche', $southRetouches->icon);
        $southPants = $this->south->services()->where('name', 'Pantalon')->firstOrFail();
        $this->assertSame(700, $southPants->priceFor($this->south->prestations()->where('name', 'Repassage')->firstOrFail()));
        $this->assertSame(1200, $southPants->priceFor($southRetouches));
        $this->assertSame(1500, $southPants->priceFor($this->south->cleaningPrestation()));
        $this->assertSame(0, $this->foreign->prestations()->where('name', 'Retouches')->count());
    }

    public function test_harmonizing_settings_copies_hours_to_other_pressings(): void
    {
        $this->north->update(['opening_hours' => ['monday' => ['open' => '08:00', 'close' => '19:00']], 'description' => 'Nettoyage à sec']);

        Livewire::actingAs($this->owner)->test(Harmonize::class)
            ->set('settingsSource', (string) $this->north->id)
            ->set('settingsFields', ['opening_hours', 'description'])
            ->set('settingsTargets', [(string) $this->south->id])
            ->call('syncSettings');

        $this->assertSame('Nettoyage à sec', $this->south->fresh()->description);
        $this->assertSame($this->north->fresh()->opening_hours, $this->south->fresh()->opening_hours);
    }

    public function test_group_clients_are_consolidated_across_pressings(): void
    {
        $loyal = Customer::factory()->create(['first_name' => 'Fatou', 'last_name' => 'Diallo']);
        $this->order($this->north, 4000, customer: $loyal);
        $this->order($this->south, 6000, customer: $loyal);

        Livewire::actingAs($this->owner)->test(Clients::class)
            ->set('multiOnly', true)
            ->assertSee('Fatou Diallo')
            ->assertSee('10 000 F')
            ->assertViewHas('sharedCustomers', 1);
    }

    public function test_group_exports_and_monthly_report_download(): void
    {
        $this->order($this->north, 5000, 5000);

        $this->actingAs($this->owner)->get(route('group.export', ['type' => 'commandes', 'format' => 'csv']))->assertOk()->assertDownload('commandes-groupe.csv');
        $this->actingAs($this->owner)->get(route('group.export', ['type' => 'paiements', 'format' => 'xlsx']))->assertOk();
        $this->actingAs($this->owner)->get(route('group.report'))->assertOk()->assertHeader('content-type', 'application/pdf');

        Livewire::actingAs($this->owner)->test(Reports::class)
            ->assertSee('Rapport PDF')
            ->call('toggleMonthlyEmail');
        $this->assertTrue($this->owner->fresh()->group_report_opt_in);
    }

    public function test_the_monthly_report_is_mailed_to_owners_who_asked_for_it(): void
    {
        Mail::fake();
        $this->owner->update(['group_report_opt_in' => true]);
        $other = User::factory()->create(['email' => 'nope@example.test']);

        $this->artisan('presslink:send-group-reports')->assertSuccessful();

        Mail::assertQueued(GroupMonthlyReport::class, fn ($mail) => $mail->hasTo('owner@example.test'));
        Mail::assertNotQueued(GroupMonthlyReport::class, fn ($mail) => $mail->hasTo($other->email));
    }

    public function test_the_palette_searches_the_whole_group(): void
    {
        $south = $this->order($this->south, lastName: 'Traoré');
        $foreign = $this->order($this->foreign, lastName: 'Traoré');

        Livewire::actingAs($this->owner)->test(CommandPalette::class)
            ->set('query', 'Traoré')
            ->assertSee($south->order_number)
            ->assertDontSee($foreign->order_number)
            ->assertSee('Pressing Sud');
    }
}
