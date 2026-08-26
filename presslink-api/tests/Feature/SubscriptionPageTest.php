<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Livewire\Subscription\Show as SubscriptionShow;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_employee_cannot_access_the_subscription_page(): void
    {
        $pressing = Pressing::factory()->create();
        $employee = $this->makeStaff($pressing, PressingRole::Employee);

        $this->actingAs($employee);

        Livewire::test(SubscriptionShow::class)->assertStatus(403);
    }

    public function test_admin_sees_plan_and_quota_during_trial(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        Subscription::factory()->for($pressing)->create([
            'plan' => SubscriptionPlan::Starter,
            'orders_limit' => SubscriptionPlan::Starter->ordersLimit(),
            'orders_used' => 3,
        ]);

        $this->actingAs($admin);

        Livewire::test(SubscriptionShow::class)
            ->assertStatus(200)
            ->assertSee('Starter')
            ->assertSee('3')
            ->assertSee('150')
            ->assertDontSee('Création de commandes bloquée');
    }

    public function test_admin_sees_a_warning_when_the_quota_is_exhausted(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        Subscription::factory()->for($pressing)->create([
            'plan' => SubscriptionPlan::Starter,
            'orders_limit' => SubscriptionPlan::Starter->ordersLimit(),
            'orders_used' => 150,
        ]);

        $this->actingAs($admin);

        Livewire::test(SubscriptionShow::class)
            ->assertStatus(200)
            ->assertSee('Création de commandes bloquée');
    }

    public function test_admin_sees_a_warning_when_the_trial_has_expired(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        Subscription::factory()->for($pressing)->create([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($admin);

        Livewire::test(SubscriptionShow::class)
            ->assertStatus(200)
            ->assertSee('Création de commandes bloquée');
    }

    public function test_admin_sees_a_fallback_message_when_no_subscription_exists(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(SubscriptionShow::class)
            ->assertStatus(200)
            ->assertSee('Aucun abonnement configuré');
    }
}
