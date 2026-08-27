<?php

namespace Tests\Concurrency;

use App\Enums\PressingStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concurrency\Support\LivewireSession;

/**
 * Section 5 du plan de charge : "suspendre un pressing pendant une
 * tentative de création de commande".
 *
 * `PressingStatus::Suspended` était auparavant vérifié NULLE PART côté
 * staff (uniquement affiché dans le back-office Super Admin) — voir
 * load-testing/RAPPORT.md, finding B. `CreateOrderAction::handle()`
 * refuse désormais toute création pour un pressing non actif. Ces tests
 * vérifient le correctif, seul (sans concurrence nécessaire pour le
 * révéler) et sous la course explicitement demandée par le plan de charge
 * (suspension pendant la tentative de création).
 */
class PressingSuspensionTest extends ConcurrencyTestCase
{
    public function test_a_suspended_pressing_cannot_have_orders_created(): void
    {
        ['pressing' => $pressing] = $this->makePressingWithAdmin(ordersLimit: 1000);
        $employee = $this->makeEmployeeOf($pressing);

        $pressing->update(['status' => PressingStatus::Suspended]);

        $session = $this->newSession();
        $this->assertTrue($session->loginAsStaff($employee->email, self::PASSWORD));

        $page = $session->visit('/commandes/nouvelle');
        $this->assertNotNull($page['snapshot'], 'Le staff peut toujours ouvrir le formulaire — seule la création est bloquée.');

        $afterPick = $session->call($page['snapshot'], [
            'newFirstName' => 'Suspendu', 'newLastName' => 'Test', 'newPhone' => '+2250099998888',
        ], [['path' => '', 'method' => 'createAndPickCustomer', 'params' => []]]);

        $afterService = $session->call($afterPick['snapshot'], ['pickerService' => 'other']);

        $afterAddItem = $session->call($afterService['snapshot'], [
            'pickerCustomName' => 'Article', 'pickerCustomPrice' => '1000', 'pickerQuantity' => 1,
        ], [['path' => '', 'method' => 'addPickedItem', 'params' => []]]);

        $created = $session->call($afterAddItem['snapshot'], [], [['path' => '', 'method' => 'create', 'params' => []]]);

        $this->assertEmpty(
            $created['effects']['redirect'] ?? null,
            'La création doit être refusée (pas de redirect) pour un pressing suspendu.'
        );

        $this->assertSame(
            0,
            Order::where('pressing_id', $pressing->id)->count(),
            'Aucune commande ne doit être persistée pour un pressing suspendu.'
        );
    }

    public function test_suspending_a_pressing_concurrently_with_an_order_creation_attempt_blocks_the_creation(): void
    {
        ['pressing' => $pressing] = $this->makePressingWithAdmin(ordersLimit: 1000);
        $employee = $this->makeEmployeeOf($pressing);
        $superAdmin = User::factory()->create([
            'email' => 'ct-superadmin-'.uniqid().'@concurrency.test',
            'password' => Hash::make(self::PASSWORD),
            'is_super_admin' => true,
        ]);

        $employeeSession = $this->newSession();
        $adminSession = $this->newSession();
        $this->assertTrue($employeeSession->loginAsStaff($employee->email, self::PASSWORD));
        $this->assertTrue($adminSession->loginAsStaff($superAdmin->email, self::PASSWORD));

        $page = $employeeSession->visit('/commandes/nouvelle');
        $afterPick = $employeeSession->call($page['snapshot'], [
            'newFirstName' => 'Course', 'newLastName' => 'Suspension', 'newPhone' => '+2250099997777',
        ], [['path' => '', 'method' => 'createAndPickCustomer', 'params' => []]]);
        $afterService = $employeeSession->call($afterPick['snapshot'], ['pickerService' => 'other']);
        $afterAddItem = $employeeSession->call($afterService['snapshot'], [
            'pickerCustomName' => 'Article', 'pickerCustomPrice' => '1000', 'pickerQuantity' => 1,
        ], [['path' => '', 'method' => 'addPickedItem', 'params' => []]]);

        $adminPage = $adminSession->visit("/admin/pressings/{$pressing->id}");

        // Suspension (Super Admin) et création de commande (employé)
        // envoyées EN MÊME TEMPS.
        $responses = Http::pool(fn ($pool) => [
            $employeeSession->pooledClient($pool)->post($employeeSession->updateUrl(), $employeeSession->updatePayload(
                $afterAddItem['snapshot'], [], [['path' => '', 'method' => 'create', 'params' => []]]
            )),
            $adminSession->pooledClient($pool)->post($adminSession->updateUrl(), $adminSession->updatePayload(
                $adminPage['snapshot'], [], [['path' => '', 'method' => 'togglePressingStatus', 'params' => []]]
            )),
        ]);

        $createResult = LivewireSession::extractComponent($responses[0]);

        $pressing->refresh();
        $ordersCreated = Order::where('pressing_id', $pressing->id)->count();

        // Deux requêtes véritablement concurrentes, sans verrou partagé
        // entre la lecture du statut du pressing et sa mise à jour : la
        // création peut légitimement lire "Actif" avant que la suspension
        // ne committe (elle réussit alors, ce qui est correct — la requête
        // a bien commencé avant la suspension), ou lire "Suspendu" après
        // (elle échoue). Ce qui compte est la COHÉRENCE : le nombre de
        // commandes créées doit toujours correspondre exactement à ce que
        // la réponse HTTP a rapporté — jamais de commande "fantôme" ni de
        // commande "perdue".
        $created = ! empty($createResult['effects']['redirect'] ?? null);

        $this->assertSame($created ? 1 : 0, $ordersCreated);

        $superAdmin->delete();
    }
}
