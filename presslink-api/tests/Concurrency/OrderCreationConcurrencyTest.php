<?php

namespace Tests\Concurrency;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Tests\Concurrency\Support\LivewireSession;

/**
 * Section 5 du plan de charge : "deux employés créent des commandes
 * simultanément". Deux VRAIES sessions HTTP (deux employés distincts du
 * même pressing) soumettent chacune le wizard de création de commande en
 * parallèle (Http::pool()) — on vérifie qu'aucune donnée ne se mélange
 * entre les deux requêtes concurrentes et que le compteur de quota de
 * l'abonnement (orders_used) reflète bien les deux créations (pas de
 * lost update sur l'incrément).
 */
class OrderCreationConcurrencyTest extends ConcurrencyTestCase
{
    public function test_two_employees_creating_orders_at_the_same_time_do_not_corrupt_each_others_data(): void
    {
        ['pressing' => $pressing, 'subscription' => $subscription] = $this->makePressingWithAdmin(ordersLimit: 1000);
        $employeeA = $this->makeEmployeeOf($pressing);
        $employeeB = $this->makeEmployeeOf($pressing);

        $sessionA = $this->newSession();
        $sessionB = $this->newSession();

        $this->assertTrue($sessionA->loginAsStaff($employeeA->email, self::PASSWORD));
        $this->assertTrue($sessionB->loginAsStaff($employeeB->email, self::PASSWORD));

        $createPageA = $sessionA->visit('/commandes/nouvelle');
        $createPageB = $sessionB->visit('/commandes/nouvelle');

        $this->assertNotNull($createPageA['snapshot']);
        $this->assertNotNull($createPageB['snapshot']);

        // Étape 1 : chaque session crée son propre client "walk-in" avec un
        // nom distinct — si les requêtes concurrentes se mélangeaient
        // (état partagé par erreur côté serveur), on retrouverait le nom
        // de A sur la commande de B ou inversement.
        $afterPickA = $sessionA->call($createPageA['snapshot'], [
            'newFirstName' => 'Alpha', 'newLastName' => 'Concurrent', 'newPhone' => $this->uniquePhone(1),
        ], [['path' => '', 'method' => 'createAndPickCustomer', 'params' => []]]);

        $afterPickB = $sessionB->call($createPageB['snapshot'], [
            'newFirstName' => 'Beta', 'newLastName' => 'Concurrent', 'newPhone' => $this->uniquePhone(2),
        ], [['path' => '', 'method' => 'createAndPickCustomer', 'params' => []]]);

        $this->assertNotNull($afterPickA['snapshot']);
        $this->assertNotNull($afterPickB['snapshot']);

        $afterServiceA = $sessionA->call($afterPickA['snapshot'], ['pickerService' => 'other']);
        $afterServiceB = $sessionB->call($afterPickB['snapshot'], ['pickerService' => 'other']);

        // Les deux appels addPickedItem() sont réellement envoyés EN
        // PARALLÈLE ici via Http::pool().
        $addItemResponses = Http::pool(fn ($pool) => [
            $sessionA->pooledClient($pool)->post($sessionA->updateUrl(), $sessionA->updatePayload($afterServiceA['snapshot'], [
                'pickerCustomName' => 'Article Alpha', 'pickerCustomPrice' => '1111', 'pickerQuantity' => 1,
            ], [['path' => '', 'method' => 'addPickedItem', 'params' => []]])),
            $sessionB->pooledClient($pool)->post($sessionB->updateUrl(), $sessionB->updatePayload($afterServiceB['snapshot'], [
                'pickerCustomName' => 'Article Beta', 'pickerCustomPrice' => '2222', 'pickerQuantity' => 1,
            ], [['path' => '', 'method' => 'addPickedItem', 'params' => []]])),
        ]);

        $afterAddItemA = LivewireSession::extractComponent($addItemResponses[0]);
        $afterAddItemB = LivewireSession::extractComponent($addItemResponses[1]);

        $this->assertNotNull($afterAddItemA['snapshot'] ?? null);
        $this->assertNotNull($afterAddItemB['snapshot'] ?? null);

        // Et les deux create() finaux, également en parallèle.
        $createResponses = Http::pool(fn ($pool) => [
            $sessionA->pooledClient($pool)->post($sessionA->updateUrl(), $sessionA->updatePayload($afterAddItemA['snapshot'], [], [['path' => '', 'method' => 'create', 'params' => []]])),
            $sessionB->pooledClient($pool)->post($sessionB->updateUrl(), $sessionB->updatePayload($afterAddItemB['snapshot'], [], [['path' => '', 'method' => 'create', 'params' => []]])),
        ]);

        $createdA = LivewireSession::extractComponent($createResponses[0]);
        $createdB = LivewireSession::extractComponent($createResponses[1]);

        $this->assertNotEmpty($createdA['effects']['redirect'] ?? null, 'La création A doit rediriger (succès).');
        $this->assertNotEmpty($createdB['effects']['redirect'] ?? null, 'La création B doit rediriger (succès).');

        $orders = Order::where('pressing_id', $pressing->id)->with('items', 'customer')->get();
        $this->assertCount(2, $orders, 'Les deux commandes concurrentes doivent exister, sans écrasement.');

        $orderAlpha = $orders->first(fn (Order $o) => $o->customer->first_name === 'Alpha');
        $orderBeta = $orders->first(fn (Order $o) => $o->customer->first_name === 'Beta');

        $this->assertNotNull($orderAlpha, 'La commande du client "Alpha" doit exister avec son propre item.');
        $this->assertNotNull($orderBeta, 'La commande du client "Beta" doit exister avec son propre item.');

        $this->assertSame('Article Alpha', $orderAlpha->items->first()->name, 'Aucune contamination croisée : item de A doit rester celui de A.');
        $this->assertSame('Article Beta', $orderBeta->items->first()->name, 'Aucune contamination croisée : item de B doit rester celui de B.');
        $this->assertSame(1111, $orderAlpha->items->first()->unit_price_fcfa);
        $this->assertSame(2222, $orderBeta->items->first()->unit_price_fcfa);

        $subscription->refresh();
        $this->assertSame(2, $subscription->orders_used, 'incrementOrdersUsed() doit être atomique : 2 créations concurrentes → orders_used = 2, pas 1 (lost update).');
    }

    private function uniquePhone(int $seed): string
    {
        return '+2250'.str_pad((string) (900_000_000 + $seed * 1000 + random_int(0, 999)), 9, '0', STR_PAD_LEFT);
    }
}
