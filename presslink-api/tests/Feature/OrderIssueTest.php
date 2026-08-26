<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderIssueStatus;
use App\Enums\PressingRole;
use App\Livewire\Orders\Show;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderIssueTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(): array
    {
        return ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1];
    }

    public function test_customer_can_report_an_issue_on_their_order(): void
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);
        $token = $customer->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/issues", [
                'category' => 'missing_item',
                'description' => 'Il manque une chemise.',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('order_issues', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'category' => 'missing_item',
            'status' => 'open',
        ]);
    }

    public function test_customer_cannot_report_an_issue_on_someone_elses_order(): void
    {
        $pressing = Pressing::factory()->create();
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($pressing, $owner, [$this->makeItem()]);
        $token = $intruder->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/issues", ['category' => 'wrong_item'])
            ->assertForbidden();
    }

    public function test_an_invalid_category_is_rejected(): void
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/issues", ['category' => 'not_a_real_category'])
            ->assertUnprocessable();
    }

    public function test_staff_can_resolve_an_issue(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = User::factory()->create();
        $pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $customer = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);
        $issue = $order->issues()->create([
            'customer_id' => $customer->id,
            'category' => 'missing_item',
        ]);

        $this->actingAs($admin);

        Livewire::test(Show::class, ['order' => $order])
            ->call('resolveIssue', $issue->id);

        $this->assertSame(OrderIssueStatus::Resolved, $issue->fresh()->status);
        $this->assertSame($admin->id, $issue->fresh()->resolved_by);
    }
}
