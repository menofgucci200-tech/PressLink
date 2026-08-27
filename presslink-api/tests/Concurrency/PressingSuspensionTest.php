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
 * tentative de création de commande". En creusant le code (voir
 * app/Livewire/Admin/Pressings/{Index,Show}.php et
 * app/Actions/Orders/CreateOrderAction.php), PressingStatus::Suspended
 * n'est en réalité vérifié NULLE PART côté staff : il ne sert qu'à
 * l'affichage dans le back-office Super Admin. Ce n'est donc même pas
 * une question de fenêtre de course — la suspension n'a aucun effet
 * bloquant, avec ou sans concurrence. On le documente ici avec le test le
 * plus simple possible (suspension AVANT la tentative, sans même avoir
 * besoin de concurrence pour le révéler), et on illustre en plus le cas
 * "concurrent" demandé (suspension pendant la création).
 */
class PressingSuspensionTest extends ConcurrencyTestCase
{
    public function test_a_suspended_pressing_can_still_have_orders_created_no_enforcement_exists(): void
    {
        ['pressing' => $pressing] = $this->makePressingWithAdmin(ordersLimit: 1000);
        $employee = $this->makeEmployeeOf($pressing);

        $pressing->update(['status' => PressingStatus::Suspended]);

        $session = $this->newSession();
        $this->assertTrue($session->loginAsStaff($employee->email, self::PASSWORD));

        $page = $session->visit('/commandes/nouvelle');
        $this->assertNotNull($page['snapshot'], 'Le staff d\'un pressing suspendu peut quand même ouvrir le formulaire de création.');

        $afterPick = $session->call($page['snapshot'], [
            'newFirstName' => 'Suspendu', 'newLastName' => 'Test', 'newPhone' => '+2250099998888',
        ], [['path' => '', 'method' => 'createAndPickCustomer', 'params' => []]]);

        $afterService = $session->call($afterPick['snapshot'], ['pickerService' => 'other']);

        $afterAddItem = $session->call($afterService['snapshot'], [
            'pickerCustomName' => 'Article', 'pickerCustomPrice' => '1000', 'pickerQuantity' => 1,
        ], [['path' => '', 'method' => 'addPickedItem', 'params' => []]]);

        $created = $session->call($afterAddItem['snapshot'], [], [['path' => '', 'method' => 'create', 'params' => []]]);

        // FINDING (à documenter en ÉLEVÉ dans le rapport) : la création
        // réussit malgré la suspension — aucune règle métier ne bloque ça.
        $this->assertNotEmpty(
            $created['effects']['redirect'] ?? null,
            'Constat : la commande est créée même si le pressing est SUSPENDU — PressingStatus::Suspended n\'est vérifié nulle part côté staff (uniquement affiché côté Super Admin). Voir RAPPORT.md.'
        );

        $this->assertSame(
            1,
            Order::where('pressing_id', $pressing->id)->count(),
            'Confirme qu\'une commande a bien été persistée pour un pressing suspendu.'
        );
    }

    public function test_suspending_a_pressing_concurrently_with_an_order_creation_attempt_has_no_effect(): void
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

        // Que la suspension gagne la course ou non, la création aboutit
        // dans tous les cas : ce n'est pas un problème de timing, c'est
        // une règle métier absente.
        $this->assertNotEmpty($createResult['effects']['redirect'] ?? null);
        $this->assertSame(1, Order::where('pressing_id', $pressing->id)->count());

        $superAdmin->delete();
    }
}
