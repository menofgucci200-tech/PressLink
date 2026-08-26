<?php

namespace Tests\Feature\EndToEnd;

use App\Enums\PressingRole;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Orders\Show as OrdersShow;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 8 — tests bout en bout des flows critiques du MVP, en conditions
 * réelles : à travers les vraies interfaces (endpoints API pour le client,
 * composants Livewire réels pour le staff), pas d'appel direct aux
 * Actions/Models internes. Chaque test suit un flow complet de bout en
 * bout, pas une unité isolée.
 */
class CriticalFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    private function bearer(Customer $customer): string
    {
        return $customer->createToken('test')->plainTextToken;
    }

    /**
     * Efface la session "web" laissée par un Livewire::actingAs($staff) —
     * sans ça, l'appel API suivant (Bearer token client) hérite du staff
     * authentifié en session, car le guard sanctum retombe sur le guard
     * web pour les requêtes considérées "stateful" en test. Un vrai client
     * mobile n'envoie jamais ce cookie ; c'est un artefact de test, pas un
     * bug de prod, mais il faut l'isoler pour tester les deux à la suite.
     */
    private function forgetWebSession(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * Flow 1 — Inscription puis connexion d'un client, de bout en bout via
     * les endpoints réellement appelés par l'app Flutter.
     */
    public function test_flow_1_customer_can_register_then_log_back_in(): void
    {
        // Un numéro inconnu : le client passe par l'inscription.
        $this->postJson('/api/v1/auth/customer/check-phone', ['phone' => '+2250701020304'])
            ->assertOk()
            ->assertJson(['exists' => false]);

        $register = $this->postJson('/api/v1/auth/customer/register', [
            'phone' => '+2250701020304',
            'first_name' => 'Awa',
            'last_name' => 'Kouassi',
            'gender' => 'femme',
            'password' => '1234',
            'password_confirmation' => '1234',
        ])->assertCreated();

        $token = $register->json('token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/customer/me')
            ->assertOk()
            ->assertJsonPath('phone', '+2250701020304');

        // Se déconnecter puis se reconnecter avec le même mot de passe.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/customer/logout')
            ->assertOk();

        $customer = Customer::where('phone', '+2250701020304')->firstOrFail();
        $this->assertSame(0, $customer->tokens()->count(), 'Le token doit être révoqué en base après déconnexion.');

        $this->postJson('/api/v1/auth/customer/check-phone', ['phone' => '+2250701020304'])
            ->assertOk()
            ->assertJson(['exists' => true]);

        // Un mauvais mot de passe doit être rejeté sans révéler d'info sur le compte.
        $this->postJson('/api/v1/auth/customer/login', [
            'phone' => '+2250701020304',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        // Un second numéro identique ne peut pas se réinscrire (unicité du téléphone).
        $this->postJson('/api/v1/auth/customer/register', [
            'phone' => '+2250701020304',
            'first_name' => 'Autre',
            'last_name' => 'Personne',
            'gender' => 'homme',
            'password' => '5678',
            'password_confirmation' => '5678',
        ])->assertUnprocessable();

        $login = $this->postJson('/api/v1/auth/customer/login', [
            'phone' => '+2250701020304',
            'password' => '1234',
        ])->assertOk();

        $newToken = $login->json('token');
        $this->assertNotEmpty($newToken);
        $this->assertNotSame($token, $newToken, 'Une nouvelle connexion doit émettre un nouveau token Sanctum.');
        $this->assertSame(1, $customer->tokens()->count());

        $this->withHeader('Authorization', "Bearer {$newToken}")
            ->getJson('/api/v1/auth/customer/me')
            ->assertOk()
            ->assertJsonPath('phone', '+2250701020304');
    }

    /**
     * Flow 2 — Un client rejoint un pressing par code, puis le pressing
     * le voit apparaître dans sa liste de clients côté dashboard.
     */
    public function test_flow_2_customer_joins_a_pressing_by_code_and_pressing_sees_them(): void
    {
        $pressing = Pressing::factory()->create(['name' => 'Pressing Élégance', 'code' => 'PE-4821']);
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Kouassi']);
        $token = $this->bearer($customer);

        // Code inexistant, rejeté avant même d'entrer un bon code.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/pressings/join', ['code' => 'XX-0000'])
            ->assertNotFound();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/pressings/join', ['code' => 'pe-4821'])
            ->assertOk()
            ->assertJson(['id' => $pressing->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/pressings/mine')
            ->assertOk()
            ->assertJsonFragment(['id' => $pressing->id]);

        $this->assertTrue($pressing->customers()->where('customers.id', $customer->id)->exists());

        // Rejoindre à nouveau le même pressing est sans effet (idempotent), pas une erreur.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/pressings/join', ['code' => 'pe-4821'])
            ->assertOk();
        $this->assertSame(1, $pressing->customers()->where('customers.id', $customer->id)->count());

        $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Awa Kouassi');
    }

    /**
     * Flow 3 — Le cœur métier : le staff crée une commande, le client la
     * voit côté app via l'API, le staff la fait progresser à travers tout
     * le cycle de statuts, et le client voit chaque changement (y compris
     * l'historique complet) en re-consultant l'API.
     */
    public function test_flow_3_order_lifecycle_from_creation_to_pickup_is_visible_to_the_customer(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $service = Service::factory()->for($pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);
        $token = $this->bearer($customer);

        // Le staff crée la commande via le vrai composant Livewire (wizard 4 étapes).
        Livewire::actingAs($admin)
            ->test(OrdersCreate::class)
            ->call('pickCustomer', $customer->id)
            ->call('next')
            ->set('pickerService', (string) $service->id)
            ->set('pickerQuantity', 2)
            ->call('addPickedItem')
            ->call('next')
            ->call('next')
            ->call('create');

        $this->forgetWebSession();

        $order = $pressing->orders()->firstOrFail();

        // Le client la voit apparaître dans sa liste et son détail, statut "reçue".
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonFragment(['order_number' => $order->order_number]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('status', 'recue');

        // Notification "commande enregistrée" déjà en base, visible côté client.
        $this->assertSame(1, $customer->notifications()->count());
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.data.title', 'Commande enregistrée');

        // Le staff fait progresser la commande à travers tout le cycle. Seuls
        // Prête et Récupérée déclenchent une notification (pas Traitement).
        foreach (['traitement', 'prete', 'recuperee'] as $status) {
            Livewire::actingAs($admin)
                ->test(OrdersShow::class, ['order' => $order])
                ->call('transitionTo', $status);
        }

        $this->forgetWebSession();

        // Le client voit le statut final et l'historique complet des 4 étapes.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('status', 'recuperee');

        $this->assertCount(4, $response->json('status_history'));

        // 3 notifications au total : créée, prête, récupérée (pas traitement).
        $this->assertSame(3, $customer->notifications()->count());
        $titles = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->json('data.*.data.title');
        $this->assertContains('Commande enregistrée', $titles);
        $this->assertContains('Votre commande est prête !', $titles);
        $this->assertContains('Commande récupérée', $titles);
    }

    /**
     * Flow 4 — Le client signale un problème sur une commande, le staff le
     * voit et le résout avec une note, le client voit la résolution.
     */
    public function test_flow_4_customer_reports_an_issue_and_staff_resolves_it(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $service = Service::factory()->for($pressing)->create(['price_fcfa' => 1000]);
        $token = $this->bearer($customer);

        Livewire::actingAs($admin)
            ->test(OrdersCreate::class)
            ->call('pickCustomer', $customer->id)
            ->call('next')
            ->set('pickerService', (string) $service->id)
            ->set('pickerQuantity', 1)
            ->call('addPickedItem')
            ->call('next')
            ->call('next')
            ->call('create');

        $this->forgetWebSession();

        $order = $pressing->orders()->firstOrFail();

        // Le client signale un article manquant via l'API réelle.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/issues", [
                'category' => 'missing_item',
                'description' => 'Il manque une chemise.',
            ])->assertCreated();

        // Le staff le voit dans le détail de la commande et le résout avec une note.
        Livewire::actingAs($admin)
            ->test(OrdersShow::class, ['order' => $order])
            ->assertSee('Il manque une chemise.');

        $issue = $order->issues()->firstOrFail();

        Livewire::actingAs($admin)
            ->test(OrdersShow::class, ['order' => $order])
            ->call('startResolving', $issue->id)
            ->set('resolutionNote', 'Chemise retrouvée, remise au client.')
            ->call('confirmResolve');

        $this->forgetWebSession();

        // Le client voit la résolution en reconsultant sa commande.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->id}/issues")
            ->assertOk()
            ->assertJsonFragment(['status' => 'resolved'])
            ->assertJsonFragment(['resolution_note' => 'Chemise retrouvée, remise au client.']);
    }

    /**
     * Flow 5 — RB-09 : un pressing dont le quota de commandes est atteint
     * ne peut plus créer de commande, et le staff voit une erreur claire
     * dans le wizard de création — pas une exception brute.
     */
    public function test_flow_5_subscription_quota_blocks_new_order_creation(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $service = Service::factory()->for($pressing)->create(['price_fcfa' => 1000]);

        Subscription::factory()->for($pressing)->create([
            'orders_limit' => 1,
            'orders_used' => 1,
        ]);

        Livewire::actingAs($admin)
            ->test(OrdersCreate::class)
            ->call('pickCustomer', $customer->id)
            ->call('next')
            ->set('pickerService', (string) $service->id)
            ->set('pickerQuantity', 1)
            ->call('addPickedItem')
            ->call('next')
            ->call('next')
            ->call('create')
            ->assertSet('errorMessage', fn ($message) => str_contains($message, 'abonnement'));

        $this->assertSame(0, $pressing->orders()->count());
    }
}
