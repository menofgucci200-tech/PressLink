<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Services\Index as ServicesIndex;
use App\Livewire\Team\Index as TeamIndex;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Application côté serveur des limites de ressources par formule
 * (Offre commerciale PressLink V1 §5) — employés, administrateurs,
 * clients, services. Le quota de commandes (RB-09) est déjà couvert
 * ailleurs (OrderBusinessRulesTest).
 */
class SubscriptionLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_legacy_plan_values_still_in_the_database_are_read_as_their_equivalent(): void
    {
        $pressing = Pressing::factory()->create();
        $subscription = Subscription::factory()->for($pressing)->create();

        // Valeurs d'avant la grille V1, écrites telles quelles en base.
        DB::table('subscriptions')->where('id', $subscription->id)->update(['plan' => 'pro']);
        $this->assertSame(SubscriptionPlan::Business, $subscription->fresh()->plan);

        DB::table('subscriptions')->where('id', $subscription->id)->update(['plan' => 'enterprise']);
        $this->assertSame(SubscriptionPlan::Premium, $subscription->fresh()->plan);

        DB::table('subscriptions')->where('id', $subscription->id)->update(['plan' => 'valeur-inconnue']);
        $this->assertSame(SubscriptionPlan::Starter, $subscription->fresh()->plan);
    }

    public function test_starter_plan_blocks_a_fourth_employee(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Starter)->create();

        // Starter autorise 3 employés — on en a déjà 3.
        $this->makeStaff($pressing, PressingRole::Employee);
        $this->makeStaff($pressing, PressingRole::Employee);
        $this->makeStaff($pressing, PressingRole::Employee);

        $this->actingAs($admin);

        Livewire::test(TeamIndex::class)
            ->set('name', 'Quatrième Employé')
            ->set('login', 'quatrieme.employe')
            ->set('password', 'mot-de-passe-solide')
            ->set('roleId', $pressing->defaultRole(Role::EMPLOYEE)->id)
            ->call('inviteEmployee')
            ->assertHasErrors(['roleId']);

        $this->assertFalse(User::where('login', 'quatrieme.employe')->exists());
    }

    public function test_starter_plan_blocks_a_second_admin(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Starter)->create();

        $this->actingAs($admin);

        Livewire::test(TeamIndex::class)
            ->set('name', 'Deuxième Admin')
            ->set('login', 'deuxieme.admin')
            ->set('password', 'mot-de-passe-solide')
            ->set('roleId', $pressing->defaultRole(Role::OWNER)->id)
            ->call('inviteEmployee')
            ->assertHasErrors(['roleId']);

        $this->assertFalse(User::where('login', 'deuxieme.admin')->exists());
    }

    public function test_business_plan_allows_more_employees_than_starter(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Business)->create();

        $this->makeStaff($pressing, PressingRole::Employee);
        $this->makeStaff($pressing, PressingRole::Employee);
        $this->makeStaff($pressing, PressingRole::Employee);

        $this->actingAs($admin);

        Livewire::test(TeamIndex::class)
            ->set('name', 'Quatrième Employé')
            ->set('login', 'quatrieme.employe.business')
            ->set('password', 'mot-de-passe-solide')
            ->set('roleId', $pressing->defaultRole(Role::EMPLOYEE)->id)
            ->call('inviteEmployee')
            ->assertHasNoErrors(['roleId']);

        $this->assertTrue(User::where('login', 'quatrieme.employe.business')->exists());
    }

    public function test_starter_plan_blocks_a_21st_service(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Starter)->create();

        $pressing->services()->createMany(
            array_map(fn ($i) => ['name' => "Service $i", 'price_fcfa' => 1000, 'is_active' => true], range(1, 20))
        );

        $this->actingAs($admin);

        Livewire::test(ServicesIndex::class)
            ->call('newPiece')
            ->set('pieceName', 'Pièce en trop')
            ->call('savePiece')
            ->assertSet('errorMessage', fn ($message) => $message !== null);

        $this->assertSame(20, $pressing->services()->count());
    }

    public function test_starter_plan_blocks_a_501st_client(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Starter)->create();

        $customers = Customer::factory()->count(500)->create();
        DB::table('pressing_customers')->insert(
            $customers->map(fn (Customer $c) => [
                'pressing_id' => $pressing->id,
                'customer_id' => $c->id,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );

        $this->actingAs($admin);

        Livewire::test(ClientsIndex::class)
            ->set('firstName', 'Client')
            ->set('lastName', 'En trop')
            ->set('phone', '+2250700000199')
            ->call('createClient')
            ->assertSet('errorMessage', fn ($message) => $message !== null);

        $this->assertSame(500, $pressing->customers()->count());
    }

    public function test_client_limit_does_not_block_a_customer_already_linked(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Starter)->create();

        $customers = Customer::factory()->count(500)->create();
        DB::table('pressing_customers')->insert(
            $customers->map(fn (Customer $c) => [
                'pressing_id' => $pressing->id,
                'customer_id' => $c->id,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );

        $this->actingAs($admin);

        $existing = $customers->first();

        Livewire::test(ClientsIndex::class)
            ->set('firstName', $existing->first_name)
            ->set('lastName', $existing->last_name)
            ->set('phone', $existing->phone)
            ->call('createClient')
            ->assertSet('errorMessage', $existing->fullName().' fait déjà partie de vos clients.');
    }

    public function test_customer_cannot_join_a_pressing_that_reached_its_client_limit(): void
    {
        $pressing = Pressing::factory()->create();
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Starter)->create();

        $customers = Customer::factory()->count(500)->create();
        DB::table('pressing_customers')->insert(
            $customers->map(fn (Customer $c) => [
                'pressing_id' => $pressing->id,
                'customer_id' => $c->id,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );

        $newCustomer = Customer::factory()->create();
        $token = $newCustomer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/pressings/join', ['code' => $pressing->code])
            ->assertStatus(403);

        $this->assertFalse($pressing->customers()->where('customers.id', $newCustomer->id)->exists());
    }
}
