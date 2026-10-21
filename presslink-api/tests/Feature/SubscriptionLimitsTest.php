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
 * Limites des formules : seul le nombre de commandes par mois est limité
 * (OrderBusinessRulesTest) ; employés, propriétaires, clients et pièces ne le
 * sont plus. Les fonctions réservées sont couvertes par SubscriptionFeaturesTest.
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

    public function test_starter_no_longer_limits_employees_owners_or_services(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Starter)->create();

        $this->makeStaff($pressing, PressingRole::Employee);
        $this->makeStaff($pressing, PressingRole::Employee);
        $this->makeStaff($pressing, PressingRole::Employee);
        $pressing->services()->createMany(
            array_map(fn ($i) => ['name' => "Service $i", 'price_fcfa' => 1000, 'is_active' => true], range(1, 20))
        );

        $this->actingAs($admin);

        Livewire::test(TeamIndex::class)
            ->set('name', 'Quatrième Employé')
            ->set('login', 'quatrieme.employe')
            ->set('password', 'mot-de-passe-solide')
            ->set('roleId', $pressing->defaultRole(Role::EMPLOYEE)->id)
            ->call('inviteEmployee')
            ->assertHasNoErrors(['roleId']);

        Livewire::test(TeamIndex::class)
            ->set('name', 'Deuxième Propriétaire')
            ->set('login', 'deuxieme.proprietaire')
            ->set('password', 'mot-de-passe-solide')
            ->set('roleId', $pressing->defaultRole(Role::OWNER)->id)
            ->call('inviteEmployee')
            ->assertHasNoErrors(['roleId']);

        Livewire::test(ServicesIndex::class)
            ->call('newPiece')
            ->set('pieceName', 'Vingt-et-unième pièce')
            ->call('savePiece')
            ->assertSet('errorMessage', null);

        $this->assertTrue(User::where('login', 'quatrieme.employe')->exists());
        $this->assertTrue(User::where('login', 'deuxieme.proprietaire')->exists());
        $this->assertSame(21, $pressing->services()->count());
    }

    public function test_starter_no_longer_limits_clients(): void
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

        // Un client rejoint le pressing depuis l'appli (avant de se connecter
        // au tableau de bord, pour que l'API reconnaisse bien le client).
        $newCustomer = Customer::factory()->create();
        $token = $newCustomer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/pressings/join', ['code' => $pressing->code])
            ->assertOk();

        $this->actingAs($admin);

        Livewire::test(ClientsIndex::class)
            ->set('firstName', 'Client')
            ->set('lastName', 'Cinq cent un')
            ->set('phone', '+2250759123456')
            ->call('createClient')
            ->assertHasNoErrors()
            ->assertSet('errorMessage', null);

        $this->assertSame(502, $pressing->customers()->count());
    }
}
