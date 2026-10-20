<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\ActivityAction;
use App\Enums\PaymentMethod;
use App\Enums\PressingRole;
use App\Livewire\Cash\Index as CashIndex;
use App\Models\CashClosing;
use App\Models\CashExpense;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dépenses de caisse : l'argent sorti de la caisse dans la journée est déduit
 * des espèces encaissées pour obtenir ce qui doit être dans la caisse à la
 * clôture, comparé à ce qui a été compté.
 */
class CashExpensesTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create();
        $this->manager = User::factory()->create(['name' => 'Gérante Awa']);
        $this->pressing->staff()->attach($this->manager, [
            'role' => PressingRole::Employee->value,
            'role_id' => $this->pressing->defaultRole(Role::MANAGER)->id,
            'is_active' => true,
        ]);
        $this->actingAs($this->manager);

        $customer = Customer::factory()->create();
        $this->pressing->customers()->attach($customer, ['joined_at' => now()]);
        $order = (new CreateOrderAction)->handle($this->pressing, $customer, [
            ['service_id' => null, 'name' => 'Costume', 'unit_price_fcfa' => 10000, 'quantity' => 1],
        ], amountPaidFcfa: 8000);
        $order->recordPayment(2000, PaymentMethod::Wave, $this->manager->id);
    }

    public function test_expenses_are_deducted_from_the_cash_expected_at_closing(): void
    {
        Livewire::test(CashIndex::class)
            ->set('expenseLabel', 'Achat de lessive')
            ->set('expenseAmount', '1500')
            ->call('addExpense')
            ->assertHasNoErrors()
            ->assertSee('Achat de lessive')
            ->assertSee('Gérante Awa')
            ->assertSee('6 500 F')
            ->set('declaredCash', '6000')
            ->call('close')
            ->assertHasNoErrors()
            ->assertSee('Journée clôturée');

        $closing = CashClosing::firstOrFail();
        $this->assertSame(8000, $closing->cash_received_fcfa);
        $this->assertSame(1500, $closing->expenses_fcfa);
        $this->assertSame(6500, $closing->expected_cash_fcfa);
        $this->assertSame(-500, $closing->difference_fcfa);
        $this->assertSame(10000, $closing->total_fcfa);
        $this->assertDatabaseHas('activity_logs', ['pressing_id' => $this->pressing->id, 'action' => ActivityAction::CashExpenseRecorded->value]);
    }

    public function test_the_payments_list_is_no_longer_shown(): void
    {
        Livewire::test(CashIndex::class)
            ->assertDontSee('Paiements du jour')
            ->assertSee('Aucune dépense ce jour.')
            ->assertSee('8 000 F');
    }

    public function test_an_expense_needs_a_reason_and_an_amount(): void
    {
        Livewire::test(CashIndex::class)
            ->set('expenseLabel', '  ')
            ->set('expenseAmount', '0')
            ->call('addExpense')
            ->assertHasErrors(['expenseLabel' => 'required', 'expenseAmount' => 'min']);

        $this->assertDatabaseCount('cash_expenses', 0);
    }

    public function test_expenses_are_frozen_once_the_day_is_closed(): void
    {
        $expense = CashExpense::create([
            'pressing_id' => $this->pressing->id, 'business_date' => today()->toDateString(),
            'amount_fcfa' => 1000, 'label' => 'Transport', 'recorded_by' => $this->manager->id,
        ]);

        Livewire::test(CashIndex::class)
            ->set('declaredCash', '7000')
            ->call('close')
            ->call('removeExpense', $expense->id)
            ->set('expenseLabel', 'Savon')
            ->set('expenseAmount', '500')
            ->call('addExpense')
            ->assertDontSee('+ Ajouter la dépense');

        $this->assertDatabaseHas('cash_expenses', ['id' => $expense->id]);
        $this->assertDatabaseCount('cash_expenses', 1);
        $this->assertSame(0, CashClosing::firstOrFail()->difference_fcfa);
    }

    public function test_removing_an_expense_is_logged(): void
    {
        $expense = CashExpense::create([
            'pressing_id' => $this->pressing->id, 'business_date' => today()->toDateString(),
            'amount_fcfa' => 1000, 'label' => 'Transport', 'recorded_by' => $this->manager->id,
        ]);

        Livewire::test(CashIndex::class)->call('removeExpense', $expense->id);

        $this->assertDatabaseMissing('cash_expenses', ['id' => $expense->id]);
        $this->assertDatabaseHas('activity_logs', ['pressing_id' => $this->pressing->id, 'action' => ActivityAction::CashExpenseRemoved->value]);
    }

    public function test_an_expense_of_another_pressing_cannot_be_removed(): void
    {
        $other = Pressing::factory()->create();
        $expense = CashExpense::create([
            'pressing_id' => $other->id, 'business_date' => today()->toDateString(),
            'amount_fcfa' => 1000, 'label' => 'Transport',
        ]);

        try {
            Livewire::test(CashIndex::class)->call('removeExpense', $expense->id);
            $this->fail('La dépense d\'un autre pressing ne doit pas être trouvée.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseHas('cash_expenses', ['id' => $expense->id]);
        }
    }

    public function test_a_role_without_the_expenses_access_cannot_record_one(): void
    {
        $closer = User::factory()->create();
        $role = $this->pressing->roles()->create(['name' => 'Caissier', 'permissions' => ['cash.close']]);
        $this->pressing->staff()->attach($closer, ['role' => PressingRole::Employee->value, 'role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($closer);

        Livewire::test(CashIndex::class)
            ->assertDontSee('+ Ajouter la dépense')
            ->set('expenseLabel', 'Savon')
            ->set('expenseAmount', '500')
            ->call('addExpense')
            ->assertForbidden();
    }
}
