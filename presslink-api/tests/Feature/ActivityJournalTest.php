<?php

namespace Tests\Feature;

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\CreateOrderAction;
use App\Enums\ActivityAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PressingRole;
use App\Livewire\Activity\Index as ActivityIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Orders\Show as OrdersShow;
use App\Livewire\Pressing\Settings as PressingSettings;
use App\Livewire\Services\Index as ServicesIndex;
use App\Livewire\Team\Index as TeamIndex;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Traçabilité : chaque action de l'équipe est inscrite au journal du pressing
 * avec son auteur (nom et rôle du moment) et l'heure ; le propriétaire le
 * consulte, le filtre et l'exporte.
 */
class ActivityJournalTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create();
        $this->customer = Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Traoré']);
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);
    }

    private function member(string $roleKey, string $name): User
    {
        $role = $this->pressing->defaultRole($roleKey);
        $user = User::factory()->create(['name' => $name, 'login' => str($name)->slug('.')->toString(), 'password' => 'secret-123']);
        $this->pressing->staff()->attach($user, [
            'role' => $role->isOwner() ? PressingRole::Admin->value : PressingRole::Employee->value,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        return $user;
    }

    private function logOf(ActivityAction $action): ActivityLog
    {
        return ActivityLog::with('user')->where('pressing_id', $this->pressing->id)->where('action', $action)->latest('id')->firstOrFail();
    }

    public function test_the_whole_life_of_an_order_is_traced_with_who_did_what(): void
    {
        $employee = $this->member(Role::EMPLOYEE, 'Marc Koffi');
        $manager = $this->member(Role::MANAGER, 'Fatou Gérante');

        $this->actingAs($employee);
        $order = (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 3],
        ], amountPaidFcfa: 1000, paymentMethod: PaymentMethod::Wave);

        $created = $this->logOf(ActivityAction::OrderCreated);
        $this->assertSame('Marc Koffi', $created->user->name);
        $this->assertSame('Employé', $created->authorRole());
        $this->assertStringContainsString("Commande {$order->order_number} créée pour Awa Traoré · 3 pièces · 3 000 F", $created->description);
        $this->assertStringContainsString('Encaissement de 1 000 F (Wave)', $this->logOf(ActivityAction::PaymentRecorded)->description);

        (new ChangeOrderStatusAction)->handle($order, OrderStatus::Traitement);
        (new ChangeOrderStatusAction)->handle($order->fresh(), OrderStatus::Prete);
        $this->assertStringContainsString('En traitement → Prête', $this->logOf(ActivityAction::OrderStatusChanged)->description);

        $this->actingAs($manager);
        (new ChangeOrderStatusAction)->handle($order->fresh(), OrderStatus::Recuperee, PaymentMethod::Cash);

        $handOver = $this->logOf(ActivityAction::OrderHandedOver);
        $this->assertSame('Fatou Gérante', $handOver->user->name);
        $this->assertSame('Gérant', $handOver->authorRole());
        $this->assertSame("Commande {$order->order_number} remise à Awa Traoré", $handOver->description);
        $this->assertSame('Fatou Gérante', $this->logOf(ActivityAction::PaymentRecorded)->user->name);
        $this->assertSame(6, ActivityLog::where('order_id', $order->id)->count()); // créée, encaissement, 2 statuts, solde, remise
    }

    public function test_customers_prices_team_roles_and_settings_are_traced(): void
    {
        $owner = $this->member(Role::OWNER, 'Awa Kouassi');
        $employee = $this->member(Role::EMPLOYEE, 'Marc Koffi');
        $this->actingAs($owner);

        Livewire::test(ClientsIndex::class)
            ->set('firstName', 'Koffi')->set('lastName', 'Bamba')->set('phone', '0708091011')
            ->call('createClient');
        $this->assertSame('Client Koffi Bamba (+225 07 08 09 10 11) ajouté', $this->logOf(ActivityAction::CustomerAdded)->description);

        $shirt = Service::factory()->for($this->pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);
        $nettoyage = $this->pressing->cleaningPrestation();
        Livewire::test(ServicesIndex::class)->call('setPrice', $shirt->id, null, $nettoyage->id, '1500');
        $this->assertSame('Prix de Chemise (Nettoyage) : 1 000 F → 1 500 F', $this->logOf(ActivityAction::CatalogChanged)->description);

        Livewire::test(TeamIndex::class)->call('changeRole', $employee->id, $this->pressing->defaultRole(Role::MANAGER)->id);
        $this->assertSame('Rôle de Marc Koffi : « Employé » → « Gérant »', $this->logOf(ActivityAction::TeamChanged)->description);

        $managerRole = $this->pressing->defaultRole(Role::MANAGER);
        Livewire::test(TeamIndex::class)->call('editRole', $managerRole->id)
            ->set('rolePermissions', array_values(array_diff($managerRole->permissionValues(), ['catalog.manage'])))
            ->call('saveRole');
        $this->assertSame('Rôle « Gérant » accès retirés : Services et prix', $this->logOf(ActivityAction::RolesChanged)->description);

        Livewire::test(PressingSettings::class)->set('city', 'Yopougon')->call('save');
        $this->assertStringStartsWith('Paramètres modifiés : ville', $this->logOf(ActivityAction::SettingsChanged)->description);
    }

    public function test_staff_logins_are_traced(): void
    {
        $this->member(Role::EMPLOYEE, 'Marc Koffi');

        Livewire::test(Login::class)->set('login', 'marc.koffi')->set('password', 'secret-123')->call('authenticate');

        $this->assertSame('Connexion de Marc Koffi', $this->logOf(ActivityAction::StaffLogin)->description);
    }

    public function test_the_owner_reads_filters_and_exports_the_journal(): void
    {
        $owner = $this->member(Role::OWNER, 'Awa Kouassi');
        $employee = $this->member(Role::EMPLOYEE, 'Marc Koffi');
        $this->actingAs($employee);
        $order = (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => null, 'name' => 'Robe', 'unit_price_fcfa' => 2000, 'quantity' => 1],
        ]);

        $this->actingAs($owner);
        $this->get(route('activity.index'))->assertOk()->assertSee('Journal d\'activité')->assertSee('Marc Koffi (Employé)');

        Livewire::test(ActivityIndex::class)
            ->set('userId', (string) $employee->id)
            ->set('category', 'orders')
            ->assertSee($order->order_number)
            ->set('category', 'payments')
            ->assertDontSee($order->order_number);

        $csv = Livewire::test(ActivityIndex::class)->call('export');
        $csv->assertFileDownloaded('journal-'.$this->pressing->code.'-'.now()->format('Y-m-d').'.csv');

        Livewire::test(OrdersShow::class, ['order' => $order])->assertSee('Journal de la commande');
    }

    public function test_managers_and_employees_do_not_see_the_journal_by_default(): void
    {
        $this->actingAs($this->member(Role::MANAGER, 'Gérante'));
        $this->get(route('activity.index'))->assertForbidden();

        $this->actingAs($this->member(Role::EMPLOYEE, 'Employé'));
        $this->get(route('activity.index'))->assertForbidden();
    }
}
