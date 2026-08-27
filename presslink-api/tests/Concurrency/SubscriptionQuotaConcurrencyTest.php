<?php

namespace Tests\Concurrency;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Tests\Concurrency\Support\LivewireSession;

/**
 * Section 5 du plan de charge : "deux requêtes consomment le dernier quota
 * disponible". CreateOrderAction::handle() fait :
 *   1. SELECT abonnement, vérifie allowsNewOrder() (orders_used < orders_limit)
 *   2. INSERT commande
 *   3. UPDATE orders_used = orders_used + 1
 * Étapes 1 et 2/3 ne sont PAS protégées par un verrou pessimiste
 * (SELECT ... FOR UPDATE) ni une contrainte CHECK en base — deux requêtes
 * qui lisent le quota "presque plein" en même temps peuvent toutes les
 * deux le voir comme disponible et créer chacune une commande, dépassant
 * la limite de l'abonnement.
 */
class SubscriptionQuotaConcurrencyTest extends ConcurrencyTestCase
{
    public function test_two_concurrent_order_creations_for_the_last_available_quota_slot(): void
    {
        // Un seul slot restant : orders_used = orders_limit - 1.
        ['pressing' => $pressing, 'subscription' => $subscription] = $this->makePressingWithAdmin(ordersLimit: 5);
        $subscription->update(['orders_used' => 4]);

        $employeeA = $this->makeEmployeeOf($pressing);
        $employeeB = $this->makeEmployeeOf($pressing);

        $sessionA = $this->newSession();
        $sessionB = $this->newSession();
        $this->assertTrue($sessionA->loginAsStaff($employeeA->email, self::PASSWORD));
        $this->assertTrue($sessionB->loginAsStaff($employeeB->email, self::PASSWORD));

        [$snapshotA, $updatePathA] = $this->prepareOrderUpToSubmission($sessionA, 'QuotaAlpha', $this->uniquePhone(1));
        [$snapshotB, $updatePathB] = $this->prepareOrderUpToSubmission($sessionB, 'QuotaBeta', $this->uniquePhone(2));

        // Les deux create() finaux, concurrents, alors qu'il ne reste
        // qu'UNE seule place dans le quota.
        $responses = Http::pool(fn ($pool) => [
            $sessionA->pooledClient($pool)->post($sessionA->updateUrl(), $sessionA->updatePayload($snapshotA, [], [['path' => '', 'method' => 'create', 'params' => []]])),
            $sessionB->pooledClient($pool)->post($sessionB->updateUrl(), $sessionB->updatePayload($snapshotB, [], [['path' => '', 'method' => 'create', 'params' => []]])),
        ]);

        $resultA = LivewireSession::extractComponent($responses[0]);
        $resultB = LivewireSession::extractComponent($responses[1]);

        $succeededA = ! empty($resultA['effects']['redirect'] ?? null);
        $succeededB = ! empty($resultB['effects']['redirect'] ?? null);
        $successCount = ($succeededA ? 1 : 0) + ($succeededB ? 1 : 0);

        $ordersCreated = Order::where('pressing_id', $pressing->id)->count();
        $subscription->refresh();

        // On documente le comportement réel plutôt que de supposer :
        // - Si l'application protège bien le quota sous concurrence,
        //   exactement 1 création réussit et orders_used = 5.
        // - Si (comme attendu vu l'absence de verrou) une race existe, les
        //   2 peuvent réussir et orders_used dépasse orders_limit (5) —
        //   c'est un dépassement de quota, à documenter en CRITIQUE/ÉLEVÉ.
        if ($successCount === 2) {
            $this->assertSame(2, $ordersCreated);
            $this->assertGreaterThan(
                $subscription->orders_limit,
                $subscription->orders_used,
                'Dépassement de quota confirmé sous concurrence : orders_used > orders_limit après 2 créations sur 1 seul slot restant.'
            );
            fwrite(STDERR, "\n[FINDING] Dépassement de quota confirmé : orders_used={$subscription->orders_used} > orders_limit={$subscription->orders_limit}\n");
        } else {
            $this->assertSame(1, $successCount, 'Le quota doit correctement rejeter la seconde création concurrente.');
            $this->assertSame(1, $ordersCreated);
            $this->assertLessThanOrEqual($subscription->orders_limit, $subscription->orders_used);
        }
    }

    /** @return array{0: string, 1: string} snapshot prêt pour create(), updatePath */
    private function prepareOrderUpToSubmission(LivewireSession $session, string $firstName, string $phone): array
    {
        $page = $session->visit('/commandes/nouvelle');
        $this->assertNotNull($page['snapshot']);

        $afterPick = $session->call($page['snapshot'], [
            'newFirstName' => $firstName, 'newLastName' => 'Concurrent', 'newPhone' => $phone,
        ], [['path' => '', 'method' => 'createAndPickCustomer', 'params' => []]]);

        $afterService = $session->call($afterPick['snapshot'], ['pickerService' => 'other']);

        $afterAddItem = $session->call($afterService['snapshot'], [
            'pickerCustomName' => 'Article', 'pickerCustomPrice' => '1000', 'pickerQuantity' => 1,
        ], [['path' => '', 'method' => 'addPickedItem', 'params' => []]]);

        $this->assertNotNull($afterAddItem['snapshot']);

        return [$afterAddItem['snapshot'], $page['updatePath']];
    }

    private function uniquePhone(int $seed): string
    {
        return '+2250'.str_pad((string) (800_000_000 + $seed * 1000 + random_int(0, 999)), 9, '0', STR_PAD_LEFT);
    }
}
