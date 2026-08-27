<?php

namespace Tests\Concurrency;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\Http;
use Tests\Concurrency\Support\LivewireSession;

/**
 * Section 5 du plan de charge : "deux employés modifient le même statut de
 * commande simultanément". OrderObserver::updating() valide la transition
 * en comparant au statut ORIGINAL du modèle Eloquent chargé pour la requête
 * en cours — pas un verrou pessimiste (SELECT ... FOR UPDATE) ni une
 * contrainte au niveau base. Ce test vérifie ce qui se passe réellement
 * quand deux transitions valides-depuis-le-même-état-de-départ sont
 * envoyées en même temps.
 */
class OrderStatusConcurrencyTest extends ConcurrencyTestCase
{
    public function test_two_concurrent_valid_transitions_from_the_same_status_do_not_corrupt_the_order(): void
    {
        ['pressing' => $pressing] = $this->makePressingWithAdmin(ordersLimit: 1000);
        $employeeA = $this->makeEmployeeOf($pressing);
        $employeeB = $this->makeEmployeeOf($pressing);
        $customer = $this->makeCustomerOf($pressing);

        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'service_variant_id' => null, 'name' => 'Chemise', 'color' => null, 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);
        $this->assertSame(OrderStatus::Recue, $order->status);

        $sessionA = $this->newSession();
        $sessionB = $this->newSession();
        $this->assertTrue($sessionA->loginAsStaff($employeeA->email, self::PASSWORD));
        $this->assertTrue($sessionB->loginAsStaff($employeeB->email, self::PASSWORD));

        $pageA = $sessionA->visit("/commandes/{$order->id}");
        $pageB = $sessionB->visit("/commandes/{$order->id}");
        $this->assertNotNull($pageA['snapshot']);
        $this->assertNotNull($pageB['snapshot']);

        // Recue → Traitement (A) et Recue → Annulee (B) : les DEUX sont des
        // transitions valides depuis le statut de départ "Recue" pris
        // isolément. Envoyées en parallèle, chaque requête recharge sa
        // propre copie de $order au début de son traitement.
        $responses = Http::pool(fn ($pool) => [
            $sessionA->pooledClient($pool)->post($sessionA->updateUrl(), $sessionA->updatePayload(
                $pageA['snapshot'], [], [['path' => '', 'method' => 'transitionTo', 'params' => ['traitement']]]
            )),
            $sessionB->pooledClient($pool)->post($sessionB->updateUrl(), $sessionB->updatePayload(
                $pageB['snapshot'], [], [['path' => '', 'method' => 'transitionTo', 'params' => ['annulee']]]
            )),
        ]);

        $resultA = LivewireSession::extractComponent($responses[0]);
        $resultB = LivewireSession::extractComponent($responses[1]);

        $this->assertSame(200, $responses[0]->status());
        $this->assertSame(200, $responses[1]->status());

        $order->refresh();
        $finalStatus = $order->status;

        // CONSTAT (à documenter dans le rapport, pas un bug qu'on corrige
        // ici) : sans verrou pessimiste, les deux transitions peuvent
        // toutes les deux être acceptées côté application puisque chacune
        // valide contre SA PROPRE lecture de "Recue" — c'est un "lost
        // update" classique : le statut final est celui de la requête qui
        // a committé en dernier, pas nécessairement celui qu'on attendrait
        // métier (ex. une annulation "perdue" par un passage en
        // traitement, ou l'inverse).
        $this->assertContains(
            $finalStatus,
            [OrderStatus::Traitement, OrderStatus::Annulee],
            'Le statut final doit être l\'une des deux transitions envoyées (pas une valeur corrompue/hybride).'
        );

        $historyCount = $order->statusHistory()->count();

        $this->assertGreaterThanOrEqual(
            2,
            $historyCount,
            "Trouvaille : sur {$historyCount} entrée(s) d'historique pour 2 transitions concurrentes envoyées, l'historique peut contenir les DEUX transitions même si le statut final n'en reflète qu'une — l'audit trail peut donc afficher une transition qui n'a \"jamais vraiment eu lieu\" du point de vue de l'état final. Voir RAPPORT.md, finding concurrence statut."
        );
    }
}
