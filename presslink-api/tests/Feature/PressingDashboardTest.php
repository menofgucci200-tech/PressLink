<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Services\Index as ServicesIndex;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PressingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_employee_cannot_access_tarifs_page(): void
    {
        $pressing = Pressing::factory()->create();
        $employee = $this->makeStaff($pressing, PressingRole::Employee);

        $this->actingAs($employee);

        Livewire::test(ServicesIndex::class)->assertStatus(403);
    }

    public function test_admin_can_access_tarifs_page(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(ServicesIndex::class)->assertStatus(200);
    }

    public function test_the_order_wizard_creates_an_order_end_to_end(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $service = Service::factory()->for($pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);

        $this->actingAs($admin);

        $component = Livewire::test(OrdersCreate::class)
            ->call('pickCustomer', $customer->id)
            ->call('next')
            ->set('pickerService', (string) $service->id)
            ->set('pickerQuantity', 2)
            ->call('addPickedItem')
            ->call('next')
            ->call('next')
            ->call('create');

        $this->assertDatabaseHas('orders', [
            'pressing_id' => $pressing->id,
            'customer_id' => $customer->id,
            'total_fcfa' => 2000,
            'status' => OrderStatus::Recue->value,
        ]);
    }

    public function test_staff_from_another_pressing_cannot_view_the_order(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $otherAdmin = $this->makeStaff($otherPressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);

        $this->actingAs($otherAdmin)
            ->get(route('orders.show', $order))
            ->assertForbidden();
    }
}
