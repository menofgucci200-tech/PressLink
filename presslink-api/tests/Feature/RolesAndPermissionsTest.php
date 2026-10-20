<?php

namespace Tests\Feature;

use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\CreateOrderAction;
use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\PressingRole;
use App\Livewire\Cash\Index as CashIndex;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Services\Index as ServicesIndex;
use App\Livewire\Team\Index as TeamIndex;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Rôles et permissions : chaque pressing a Propriétaire (tous les accès),
 * Gérant et Employé ; le propriétaire coche les accès et crée ses rôles. Les
 * revenus (chiffre d'affaires, historique de caisse, exports) sont réservés
 * au propriétaire par défaut.
 */
class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);
    }

    private function member(string $roleKey, string $name = 'Membre'): User
    {
        $role = $this->pressing->defaultRole($roleKey);
        $user = User::factory()->create(['name' => $name]);
        $this->pressing->staff()->attach($user, [
            'role' => $role->isOwner() ? PressingRole::Admin->value : PressingRole::Employee->value,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        return $user;
    }

    private function order(int $paid = 0): Order
    {
        return (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => null, 'name' => 'Costume', 'unit_price_fcfa' => 5000, 'quantity' => 1],
        ], amountPaidFcfa: $paid);
    }

    public function test_every_pressing_starts_with_owner_manager_and_employee(): void
    {
        $this->assertSame(['Propriétaire', 'Gérant', 'Employé'], $this->pressing->roles()->pluck('name')->all());
        $this->assertTrue($this->pressing->defaultRole(Role::OWNER)->allows(Permission::RevenueView));
        $this->assertFalse($this->pressing->defaultRole(Role::MANAGER)->allows(Permission::RevenueView));
        $this->assertTrue($this->pressing->defaultRole(Role::MANAGER)->allows(Permission::CashClose));
        $this->assertFalse($this->pressing->defaultRole(Role::EMPLOYEE)->allows(Permission::CashClose));
    }

    public function test_legacy_admins_and_employees_keep_their_access(): void
    {
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $this->pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $this->pressing->staff()->attach($employee, ['role' => PressingRole::Employee->value, 'is_active' => true]);

        $this->assertSame('Propriétaire', $admin->roleLabelIn($this->pressing));
        $this->assertTrue($admin->may(Permission::ReportsExport, $this->pressing));
        $this->assertSame('Employé', $employee->roleLabelIn($this->pressing));
        $this->assertTrue($employee->may(Permission::OrdersCreate, $this->pressing));
        $this->assertFalse($employee->may(Permission::RevenueView, $this->pressing));
    }

    public function test_an_employee_does_not_see_revenue_exports_or_cash_totals(): void
    {
        $employee = $this->member(Role::EMPLOYEE, 'Awa Employée');
        $other = $this->member(Role::EMPLOYEE, 'Koffi');
        $this->actingAs($employee);
        $order = $this->order();
        $order->recordPayment(1500, PaymentMethod::Cash, $employee->id);
        $order->recordPayment(2500, PaymentMethod::Wave, $other->id);

        $this->get(route('dashboard'))->assertOk()->assertDontSee('Encaissé ·')->assertDontSee('Exporter')->assertSee('Awa Employée');
        $this->get(route('orders.export', ['format' => 'csv']))->assertForbidden();

        Livewire::test(CashIndex::class, ['day' => now()->subDay()->toDateString()])
            ->assertSee('Mes encaissements du jour (1)')
            ->assertDontSee('Encaissé ce jour')
            ->assertDontSee('Dernières clôtures')
            ->assertDontSee('Clôturer la caisse')
            ->call('close')
            ->assertForbidden();
    }

    public function test_the_manager_closes_today_but_sees_no_history_nor_revenue(): void
    {
        $manager = $this->member(Role::MANAGER, 'Gérante');
        $this->actingAs($manager);
        $this->order(3000);

        $this->get(route('dashboard'))->assertOk()->assertDontSee('Encaissé ·')->assertSee('Gérant');
        $this->get(route('orders.export', ['format' => 'pdf']))->assertForbidden();
        Livewire::test(ServicesIndex::class)->assertOk();

        Livewire::test(CashIndex::class, ['day' => now()->subDays(3)->toDateString()])
            ->assertSet('day', now()->subDays(3)->toDateString())
            ->assertSee('Espèces attendues')
            ->assertDontSee('Dernières clôtures')
            ->assertDontSee('Encaissé ce jour')
            ->set('declaredCash', '3000')
            ->call('close')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_closings', ['pressing_id' => $this->pressing->id, 'business_date' => today()->toDateString().' 00:00:00', 'difference_fcfa' => 0]);
    }

    public function test_only_owner_and_manager_cancel_an_order_in_progress(): void
    {
        $employee = $this->member(Role::EMPLOYEE);
        $manager = $this->member(Role::MANAGER);
        $this->actingAs($manager);
        $order = $this->order();
        (new ChangeOrderStatusAction)->handle($order, OrderStatus::Traitement);

        $this->assertFalse(CancelOrderAction::canCancel($employee, $order->fresh()));
        $this->assertTrue(CancelOrderAction::canCancel($manager, $order->fresh()));
    }

    public function test_the_manager_manages_employees_only(): void
    {
        $owner = $this->member(Role::OWNER, 'Propriétaire');
        $manager = $this->member(Role::MANAGER, 'Gérante');
        $employee = $this->member(Role::EMPLOYEE, 'Employé');
        $this->actingAs($manager);

        $component = Livewire::test(TeamIndex::class)
            ->assertViewHas('assignableRoles', fn ($roles) => $roles->pluck('key')->all() === [Role::EMPLOYEE])
            ->assertDontSee('Rôles et accès');

        $component->call('toggleActive', $owner->id)->assertForbidden();
        Livewire::test(TeamIndex::class)->call('changeRole', $employee->id, $this->pressing->defaultRole(Role::OWNER)->id)->assertForbidden();
        Livewire::test(TeamIndex::class)->call('newRole')->assertForbidden();

        Livewire::test(TeamIndex::class)->call('toggleActive', $employee->id);
        $this->assertFalse((bool) $this->pressing->staff()->whereKey($employee->id)->first()->pivot->is_active);
    }

    public function test_the_owner_creates_a_custom_role_with_chosen_access(): void
    {
        $owner = $this->member(Role::OWNER);
        $this->actingAs($owner);

        Livewire::test(TeamIndex::class)
            ->call('newRole')
            ->set('roleName', 'Caissière')
            ->set('rolePermissions', [Permission::PaymentsRecord->value, Permission::CashOwnPayments->value])
            ->call('saveRole')
            ->assertHasNoErrors();

        $role = $this->pressing->roles()->where('name', 'Caissière')->firstOrFail();
        $cashier = User::factory()->create();
        $this->pressing->staff()->attach($cashier, ['role' => PressingRole::Employee->value, 'role_id' => $role->id, 'is_active' => true]);

        $this->assertTrue($cashier->may(Permission::PaymentsRecord, $this->pressing));
        $this->assertFalse($cashier->may(Permission::OrdersCreate, $this->pressing));

        $this->actingAs($cashier);
        Livewire::test(OrdersCreate::class)->assertForbidden();
        $this->expectException(RuntimeException::class);
        $this->order();
    }

    public function test_the_owner_adjusts_the_manager_but_never_the_owner_role(): void
    {
        $this->actingAs($this->member(Role::OWNER));
        $managerRole = $this->pressing->defaultRole(Role::MANAGER);

        Livewire::test(TeamIndex::class)
            ->call('editRole', $managerRole->id)
            ->set('rolePermissions', [...$managerRole->permissionValues(), Permission::RevenueView->value])
            ->call('saveRole');

        $this->assertTrue($managerRole->fresh()->allows(Permission::RevenueView));

        Livewire::test(TeamIndex::class)->call('editRole', $this->pressing->defaultRole(Role::OWNER)->id)->assertForbidden();
    }

    public function test_the_last_owner_cannot_lose_the_owner_role(): void
    {
        $owner = $this->member(Role::OWNER);
        $second = $this->member(Role::OWNER);
        $this->actingAs($owner);

        Livewire::test(TeamIndex::class)->call('changeRole', $second->id, $this->pressing->defaultRole(Role::MANAGER)->id);
        $this->assertSame('Gérant', $second->fresh()->roleLabelIn($this->pressing));
        $this->assertSame(PressingRole::Employee, $this->pressing->staff()->whereKey($second->id)->first()->pivot->role);
    }

    public function test_a_role_given_to_members_cannot_be_deleted(): void
    {
        $this->actingAs($this->member(Role::OWNER));
        $role = $this->pressing->roles()->create(['name' => 'Repasseur', 'permissions' => [Permission::OrdersWorkshop->value]]);
        $worker = User::factory()->create();
        $this->pressing->staff()->attach($worker, ['role' => PressingRole::Employee->value, 'role_id' => $role->id, 'is_active' => true]);

        Livewire::test(TeamIndex::class)->call('deleteRole', $role->id)->assertSet('errorMessage', fn ($m) => str_contains($m, 'attribué'));
        Livewire::test(TeamIndex::class)->call('deleteRole', $this->pressing->defaultRole(Role::MANAGER)->id)->assertSet('errorMessage', fn ($m) => str_contains($m, 'ne se suppriment pas'));
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_a_workshop_only_role_cannot_hand_over_an_order(): void
    {
        $owner = $this->member(Role::OWNER);
        $role = $this->pressing->roles()->create(['name' => 'Repasseur', 'permissions' => [Permission::OrdersWorkshop->value]]);
        $worker = User::factory()->create();
        $this->pressing->staff()->attach($worker, ['role' => PressingRole::Employee->value, 'role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($owner);
        $order = $this->order();

        $this->actingAs($worker);
        (new ChangeOrderStatusAction)->handle($order, OrderStatus::Traitement);
        (new ChangeOrderStatusAction)->handle($order->fresh(), OrderStatus::Prete);

        $this->expectExceptionMessage('remettre une commande');
        (new ChangeOrderStatusAction)->handle($order->fresh(), OrderStatus::Recuperee);
    }

    public function test_cancel_reason_flow_still_requires_the_cancel_permission(): void
    {
        $owner = $this->member(Role::OWNER);
        $role = $this->pressing->roles()->create(['name' => 'Accueil', 'permissions' => [Permission::OrdersCreate->value]]);
        $desk = User::factory()->create();
        $this->pressing->staff()->attach($desk, ['role' => PressingRole::Employee->value, 'role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($owner);
        $order = $this->order();

        $this->actingAs($desk);
        $this->expectExceptionMessage('ne permet pas d\'annuler');
        (new CancelOrderAction)->handle($order, $desk, CancellationReason::EntryError);
    }
}
