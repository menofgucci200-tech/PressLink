<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Livewire\Clients\Show as ClientsShow;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientDetailTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_staff_can_view_client_detail_with_order_history_and_total_spent(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 2],
        ]);

        $cancelled = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Pantalon', 'unit_price_fcfa' => 1500, 'quantity' => 1],
        ]);
        $cancelled->update(['status' => OrderStatus::Annulee]);

        $this->actingAs($admin);

        Livewire::test(ClientsShow::class, ['customer' => $customer])
            ->assertStatus(200)
            ->assertSee($customer->fullName())
            ->assertSee($order->order_number)
            ->assertSee($cancelled->order_number)
            ->assertSee('2 000 F');
    }

    public function test_staff_from_another_pressing_cannot_view_the_client(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $otherAdmin = $this->makeStaff($otherPressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        $this->actingAs($otherAdmin)
            ->get(route('clients.show', $customer))
            ->assertForbidden();
    }
}
