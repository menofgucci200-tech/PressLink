<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderIssueCategory;
use App\Enums\OrderIssueStatus;
use App\Enums\PressingRole;
use App\Livewire\Issues\Index as IssuesIndex;
use App\Models\Customer;
use App\Models\OrderIssue;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IssuesDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_staff_sees_only_open_issues_from_their_own_pressing_by_default(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);

        $openIssue = OrderIssue::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'category' => OrderIssueCategory::MissingItem,
            'description' => 'Il manque une chemise',
            'status' => OrderIssueStatus::Open,
        ]);

        $resolvedIssue = OrderIssue::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'category' => OrderIssueCategory::DamagedItem,
            'description' => 'Chemise abîmée',
            'status' => OrderIssueStatus::Resolved,
        ]);

        // Signalement d'un autre pressing : ne doit jamais apparaître.
        $otherCustomer = Customer::factory()->create();
        $otherPressing->customers()->attach($otherCustomer, ['joined_at' => now()]);
        $otherOrder = (new CreateOrderAction)->handle($otherPressing, $otherCustomer, [
            ['service_id' => null, 'name' => 'Pantalon', 'unit_price_fcfa' => 1500, 'quantity' => 1],
        ]);
        $foreignIssue = OrderIssue::create([
            'order_id' => $otherOrder->id,
            'customer_id' => $otherCustomer->id,
            'category' => OrderIssueCategory::Other,
            'status' => OrderIssueStatus::Open,
        ]);

        $this->assertNotNull($foreignIssue->id);

        Livewire::actingAs($admin)
            ->test(IssuesIndex::class)
            ->assertSee($openIssue->description)
            ->assertDontSee($resolvedIssue->description)
            ->assertDontSee($otherOrder->order_number)
            ->assertSet('status', 'open');
    }

    public function test_staff_can_resolve_an_open_issue(): void
    {
        $pressing = Pressing::factory()->create();
        $employee = $this->makeStaff($pressing, PressingRole::Employee);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);

        $issue = OrderIssue::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'category' => OrderIssueCategory::MissingItem,
            'status' => OrderIssueStatus::Open,
        ]);

        Livewire::actingAs($employee)
            ->test(IssuesIndex::class)
            ->call('startResolving', $issue->id)
            ->set('resolutionNote', 'Chemise retrouvée et remise au client.')
            ->call('confirmResolve');

        $issue->refresh();
        $this->assertSame(OrderIssueStatus::Resolved, $issue->status);
        $this->assertSame($employee->id, $issue->resolved_by);
        $this->assertNotNull($issue->resolved_at);
        $this->assertSame('Chemise retrouvée et remise au client.', $issue->resolution_note);
    }

    public function test_staff_cannot_resolve_an_issue_from_another_pressing(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $otherCustomer = Customer::factory()->create();
        $otherPressing->customers()->attach($otherCustomer, ['joined_at' => now()]);

        $otherOrder = (new CreateOrderAction)->handle($otherPressing, $otherCustomer, [
            ['service_id' => null, 'name' => 'Pantalon', 'unit_price_fcfa' => 1500, 'quantity' => 1],
        ]);

        $foreignIssue = OrderIssue::create([
            'order_id' => $otherOrder->id,
            'customer_id' => $otherCustomer->id,
            'category' => OrderIssueCategory::Other,
            'status' => OrderIssueStatus::Open,
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($admin)
            ->test(IssuesIndex::class)
            ->call('startResolving', $foreignIssue->id)
            ->call('confirmResolve');
    }
}
