<?php

namespace Tests\Concurrency;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\Http;
use Tests\Concurrency\Support\LivewireSession;

/**
 * Section 5 du plan de charge : "deux employés modifient le même statut de
 * commande simultanément".
 *
 * `Orders\Show::transitionTo()` prend désormais un verrou pessimiste
 * (`lockForUpdate()`) et relit le statut sous ce verrou avant de valider
 * la transition (voir load-testing/RAPPORT.md, finding D — corrigé). Les
 * deux requêtes concurrentes se sérialisent donc sur ce verrou : la
 * seconde voit le statut RÉELLEMENT à jour (celui laissé par la première),
 * pas une copie potentiellement obsolète — plus de lost update ni
 * d'incohérence entre l'historique et le statut final.
 */
class OrderStatusConcurrencyTest extends ConcurrencyTestCase
{
    public function test_two_concurrent_transitions_from_the_same_status_serialize_without_lost_update(): void
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

        $this->assertContains(
            $order->status,
            [OrderStatus::Traitement, OrderStatus::Annulee],
            'Le statut final doit être l\'une des deux transitions envoyées (pas une valeur corrompue/hybride).'
        );

        // Le verrou garantit une sérialisation stricte : chaque entrée de
        // l'historique doit être une transition RÉELLEMENT valide depuis
        // la précédente, et le statut final doit correspondre à la
        // dernière entrée. Si la 2e requête (sous verrou) voit un statut
        // qui ne permet plus sa transition demandée (ex. "Annulee" est un
        // état terminal), elle est refusée — c'est le comportement
        // attendu, pas une erreur.
        // La toute première entrée est l'état initial posé par
        // OrderObserver::created() (Recue) — ce n'est pas une transition,
        // on la saute.
        $history = $order->statusHistory()->orderBy('created_at')->orderBy('id')->get()->skip(1);
        $previous = OrderStatus::Recue;

        foreach ($history as $entry) {
            $this->assertTrue(
                $previous->canTransitionTo($entry->status),
                "Historique incohérent : transition {$previous->value} → {$entry->status->value} n'est pas valide."
            );
            $previous = $entry->status;
        }

        $this->assertSame(
            $previous,
            $order->status,
            'Le statut final doit correspondre à la dernière entrée de l\'historique (plus de lost update).'
        );
    }
}
