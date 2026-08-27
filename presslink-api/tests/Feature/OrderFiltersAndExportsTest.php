<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderFiltersAndExportsTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    private function makeCustomerOf(Pressing $pressing): Customer
    {
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        return $customer;
    }

    private function makeOrder(Pressing $pressing, string $orderNumber, OrderStatus $status, string $droppedOffAt): void
    {
        $customer = $this->makeCustomerOf($pressing);
        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);
        $order->forceFill(['order_number' => $orderNumber, 'dropped_off_at' => $droppedOffAt])->save();

        // Respecte la machine à états (Recue -> Traitement -> Prete -> Recuperee).
        foreach ($this->transitionPathTo($status) as $step) {
            $order->update(['status' => $step]);
        }
    }

    /** @return list<OrderStatus> */
    private function transitionPathTo(OrderStatus $target): array
    {
        return match ($target) {
            OrderStatus::Recue => [],
            OrderStatus::Traitement, OrderStatus::Attente, OrderStatus::Annulee => [$target],
            OrderStatus::Prete => [OrderStatus::Traitement, OrderStatus::Prete],
            OrderStatus::Recuperee => [OrderStatus::Traitement, OrderStatus::Prete, OrderStatus::Recuperee],
        };
    }

    public function test_status_filter_narrows_the_orders_list(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $this->makeOrder($pressing, 'PL-000001', OrderStatus::Recue, '2026-01-10');
        $this->makeOrder($pressing, 'PL-000002', OrderStatus::Prete, '2026-01-11');

        $this->actingAs($admin);

        Livewire::test(OrdersIndex::class)
            ->set('status', OrderStatus::Prete->value)
            ->assertSee('PL-000002')
            ->assertDontSee('PL-000001');
    }

    public function test_search_filter_matches_order_number_or_customer(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $this->makeOrder($pressing, 'PL-777777', OrderStatus::Recue, '2026-01-10');
        $this->makeOrder($pressing, 'PL-888888', OrderStatus::Recue, '2026-01-11');

        $this->actingAs($admin);

        Livewire::test(OrdersIndex::class)
            ->set('search', '777777')
            ->assertSee('PL-777777')
            ->assertDontSee('PL-888888');
    }

    public function test_date_range_filter_narrows_the_orders_list(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $this->makeOrder($pressing, 'PL-000001', OrderStatus::Recue, '2026-01-05');
        $this->makeOrder($pressing, 'PL-000002', OrderStatus::Recue, '2026-02-15');

        $this->actingAs($admin);

        Livewire::test(OrdersIndex::class)
            ->set('dateFrom', '2026-02-01')
            ->set('dateTo', '2026-02-28')
            ->assertSee('PL-000002')
            ->assertDontSee('PL-000001');
    }

    public function test_reset_filters_clears_search_status_and_dates(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(OrdersIndex::class)
            ->set('search', 'x')
            ->set('status', OrderStatus::Prete->value)
            ->set('dateFrom', '2026-01-01')
            ->set('dateTo', '2026-01-31')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('status', '')
            ->assertSet('dateFrom', '')
            ->assertSet('dateTo', '');
    }

    public function test_csv_export_respects_the_current_filters(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $this->makeOrder($pressing, 'PL-111111', OrderStatus::Recue, '2026-01-10');
        $this->makeOrder($pressing, 'PL-222222', OrderStatus::Prete, '2026-01-11');

        $this->actingAs($admin);

        $response = $this->get(route('orders.export', [
            'format' => 'csv',
            'status' => OrderStatus::Prete->value,
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=commandes.csv');

        $content = $response->streamedContent();
        $this->assertStringContainsString('PL-222222', $content);
        $this->assertStringNotContainsString('PL-111111', $content);
    }

    public function test_csv_export_without_filters_includes_every_order(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $this->makeOrder($pressing, 'PL-333333', OrderStatus::Recue, '2026-01-10');
        $this->makeOrder($pressing, 'PL-444444', OrderStatus::Prete, '2026-01-11');

        $this->actingAs($admin);

        $content = $this->get(route('orders.export', ['format' => 'csv']))->streamedContent();

        $this->assertStringContainsString('PL-333333', $content);
        $this->assertStringContainsString('PL-444444', $content);
    }

    public function test_xlsx_export_downloads_a_file(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $this->makeOrder($pressing, 'PL-555555', OrderStatus::Recue, '2026-01-10');

        $this->actingAs($admin);

        $this->get(route('orders.export', ['format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=commandes.xlsx');
    }

    public function test_pdf_export_downloads_a_file(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $this->makeOrder($pressing, 'PL-666666', OrderStatus::Recue, '2026-01-10');

        $this->actingAs($admin);

        $response = $this->get(route('orders.export', ['format' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_never_includes_another_pressings_orders(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $this->makeOrder($pressing, 'PL-999999', OrderStatus::Recue, '2026-01-10');

        $otherPressing = Pressing::factory()->create();
        $this->makeOrder($otherPressing, 'PL-000000', OrderStatus::Recue, '2026-01-10');

        $this->actingAs($admin);

        $content = $this->get(route('orders.export', ['format' => 'csv']))->streamedContent();

        $this->assertStringContainsString('PL-999999', $content);
        $this->assertStringNotContainsString('PL-000000', $content);
    }

    public function test_staff_without_a_pressing_cannot_export(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('orders.export', ['format' => 'csv']))
            ->assertForbidden();
    }

    public function test_an_invalid_export_format_is_rejected(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin)
            ->get('/commandes/export/doc')
            ->assertNotFound();
    }
}
