<?php

namespace Tests\Concurrency;

use App\Models\Customer;
use App\Models\Pressing;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/**
 * Section 5 du plan de charge : "deux requêtes rejoignent le même pressing
 * simultanément". PressingController::join() utilise
 * syncWithoutDetaching() qui n'est pas atomique (SELECT puis INSERT) —
 * mais la table pressing_customers a une contrainte UNIQUE(pressing_id,
 * customer_id) en base. Ce test envoie deux vraies requêtes API
 * concurrentes (même client, même pressing) et vérifie que la contrainte
 * unique protège bien contre un doublon, sans planter en 500.
 */
class PressingJoinConcurrencyTest extends ConcurrencyTestCase
{
    public function test_the_same_customer_double_joining_a_pressing_concurrently_does_not_duplicate_or_crash(): void
    {
        $pressing = Pressing::factory()->create(['name' => 'CT Join Pressing '.uniqid()]);
        $this->trackPressing($pressing);

        $customer = Customer::factory()->create(['password' => Hash::make(self::PASSWORD)]);

        $loginRes = Http::post($this->baseUrl().'/api/v1/auth/customer/login', [
            'phone' => $customer->phone,
            'password' => self::PASSWORD,
        ]);
        $this->assertSame(200, $loginRes->status());
        $token = $loginRes->json('token');
        $this->assertNotEmpty($token);

        $authedPool = fn ($pool) => $pool->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ]);

        $responses = Http::pool(fn ($pool) => [
            $authedPool($pool)->post($this->baseUrl().'/api/v1/pressings/join', ['code' => $pressing->code]),
            $authedPool($pool)->post($this->baseUrl().'/api/v1/pressings/join', ['code' => $pressing->code]),
        ]);

        foreach ($responses as $i => $res) {
            $this->assertContains(
                $res->status(),
                [200, 201, 409, 422],
                "La requête concurrente #{$i} de double-adhésion doit être gérée proprement (2xx idempotent ou 4xx métier), jamais une 500 — obtenu {$res->status()}. Body: {$res->body()}"
            );
        }

        $membershipCount = $pressing->customers()->where('customers.id', $customer->id)->count();
        $this->assertSame(1, $membershipCount, 'La contrainte UNIQUE(pressing_id, customer_id) doit garantir une seule adhésion malgré la course.');
    }
}
