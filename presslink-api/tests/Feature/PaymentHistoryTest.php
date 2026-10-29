<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Payments\Index as AdminPayments;
use App\Livewire\Admin\Requests\Index as AdminRequests;
use App\Livewire\Subscription\Checkout;
use App\Models\PaymentAttempt;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Historique des paiements : chaque tentative de paiement en ligne (réussie,
 * refusée, abandonnée, expirée) et chaque paiement enregistré par l'équipe,
 * avec sa référence PressLink ; taux de réussite par moyen et par pressing
 * sur la vue globale, et page « Paiements » de la console.
 */
class PaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_presslink';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.geniuspay.base_url' => 'https://geniuspay.test/api/v1/merchant',
            'services.geniuspay.sandbox' => true,
            'services.geniuspay.api_key' => 'pk_sandbox_test',
            'services.geniuspay.api_secret' => 'sk_sandbox_test',
            'services.geniuspay.webhook_secret' => self::SECRET,
        ]);
        Http::preventStrayRequests();

        $this->admin = User::factory()->create(['is_super_admin' => true]);
    }

    /** @return array{0: User, 1: SubscriptionRequest} */
    private function pendingRenewal(string $pressingName = 'Pressing Étoile', int $amount = 20000): array
    {
        $pressing = Pressing::factory()->create(['name' => $pressingName]);
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Starter)->create(['current_period_ends_at' => now()->addDays(3)]);
        $owner = User::factory()->create();
        $pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return [$owner, SubscriptionRequest::create([
            'pressing_id' => $pressing->id,
            'requested_by' => $owner->id,
            'kind' => SubscriptionRequest::KIND_RENEWAL,
            'plan' => SubscriptionPlan::Starter,
            'cycle' => SubscriptionRequest::MONTHLY,
            'amount_fcfa' => $amount,
            'status' => SubscriptionRequest::PENDING,
        ])];
    }

    private function fakeCheckout(string ...$references): void
    {
        $sequence = Http::sequence();
        foreach ($references as $reference) {
            $sequence->push(['success' => true, 'data' => ['reference' => $reference, 'checkout_url' => 'https://geniuspay.ci/checkout/'.$reference, 'status' => 'pending', 'environment' => 'sandbox']], 201);
        }
        Http::fake(['geniuspay.test/api/v1/merchant/payments' => $sequence]);
    }

    /** @param  array<string, mixed>  $transaction */
    private function postWebhook(array $transaction, string $event): TestResponse
    {
        $body = json_encode(['event' => $event, 'timestamp' => now()->toIso8601String(), 'data' => ['transaction' => $transaction, 'environment' => 'sandbox']]);
        $timestamp = (string) now()->timestamp;

        return $this->call('POST', route('webhooks.geniuspay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET),
            'HTTP_X_WEBHOOK_ENVIRONMENT' => 'sandbox',
        ], $body);
    }

    public function test_each_online_attempt_gets_a_presslink_reference_and_its_outcome_is_kept(): void
    {
        [$owner, $request] = $this->pendingRenewal();
        $this->fakeCheckout('SANDBOX-AAA', 'SANDBOX-BBB');

        $checkout = Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request]);
        $checkout->call('pay');

        $first = PaymentAttempt::sole();
        $this->assertMatchesRegularExpression('/^PL-\d{6}-[2-9A-HJ-NP-Z]{5}$/', $first->reference);
        $this->assertSame(PaymentAttempt::PENDING, $first->status);
        $this->assertSame('SANDBOX-AAA', $first->gateway_reference);
        $this->assertSame('wave', $first->payment_method);
        $this->assertSame(20300, $first->total_fcfa);

        // Le pressing change de moyen : la première tentative est abandonnée.
        $checkout->set('method', 'card')->call('pay');
        $this->assertSame(PaymentAttempt::CANCELLED, $first->fresh()->status);
        $this->assertSame('Autre moyen de paiement choisi', $first->fresh()->failure_reason);

        // La carte est refusée, avec le motif de GeniusPay.
        $this->postWebhook(['reference' => 'SANDBOX-BBB', 'amount' => 20500, 'status' => 'failed', 'failure_reason' => 'Carte refusée par la banque'], 'payment.failed')->assertOk();
        $second = PaymentAttempt::where('gateway_reference', 'SANDBOX-BBB')->sole();
        $this->assertSame(PaymentAttempt::FAILED, $second->status);
        $this->assertSame('Carte refusée par la banque', $second->failure_reason);
        $this->assertNotSame($first->reference, $second->reference);
    }

    public function test_a_successful_payment_is_kept_as_paid_with_its_fees_and_net(): void
    {
        [$owner, $request] = $this->pendingRenewal();
        $this->fakeCheckout('SANDBOX-AAA');
        Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request])->call('pay');

        $this->postWebhook(['reference' => 'SANDBOX-AAA', 'amount' => 20300, 'status' => 'completed', 'payment_method' => 'wave', 'fees' => 405, 'net_amount' => 19895], 'payment.success')->assertOk();
        $this->postWebhook(['reference' => 'SANDBOX-AAA', 'amount' => 20300, 'status' => 'completed', 'payment_method' => 'wave', 'fees' => 405, 'net_amount' => 19895], 'payment.success')->assertOk();

        $attempt = PaymentAttempt::sole();
        $this->assertSame(PaymentAttempt::PAID, $attempt->status);
        $this->assertSame(20300, $attempt->paid_amount_fcfa);
        $this->assertSame(405, $attempt->gateway_fees_fcfa);
        $this->assertSame(19895, $attempt->net_received_fcfa);
        $this->assertNotNull($attempt->completed_at);
    }

    public function test_a_payment_page_left_without_answer_expires(): void
    {
        [$owner, $request] = $this->pendingRenewal();
        $this->fakeCheckout('SANDBOX-AAA');
        Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request])->call('pay');

        $this->travel(SubscriptionRequest::ONLINE_PAYMENT_HOURS + 1)->hours();
        $this->artisan('presslink:expire-payments')->assertSuccessful();

        $this->assertSame(PaymentAttempt::EXPIRED, PaymentAttempt::sole()->status);
    }

    public function test_a_payment_recorded_by_the_team_joins_the_history(): void
    {
        [, $request] = $this->pendingRenewal();

        Livewire::actingAs($this->admin)->test(AdminRequests::class)->call('applyRequest', $request->id);

        $attempt = PaymentAttempt::sole();
        $this->assertSame(PaymentAttempt::MANUAL, $attempt->channel);
        $this->assertSame(PaymentAttempt::PAID, $attempt->status);
        $this->assertSame(20000, $attempt->paid_amount_fcfa);
        $this->assertSame('Enregistré par l\'équipe', $attempt->methodLabel());
    }

    public function test_the_dashboard_shows_success_rates_by_method_and_by_pressing(): void
    {
        [, $etoile] = $this->pendingRenewal('Pressing Étoile');
        [, $lagune] = $this->pendingRenewal('Pressing Lagune');
        $attempt = fn (SubscriptionRequest $request, string $method, string $status) => PaymentAttempt::create([
            'reference' => PaymentAttempt::newReference(), 'subscription_request_id' => $request->id, 'pressing_id' => $request->pressing_id,
            'pressing_name' => $request->pressing->name, 'channel' => PaymentAttempt::ONLINE, 'payment_method' => $method, 'status' => $status,
            'amount_fcfa' => 20000, 'fee_fcfa' => 300, 'total_fcfa' => 20300, 'paid_amount_fcfa' => $status === PaymentAttempt::PAID ? 20300 : null,
            'started_at' => now()->subDay(),
        ]);
        $attempt($etoile, 'wave', PaymentAttempt::PAID);
        $attempt($etoile, 'wave', PaymentAttempt::PAID);
        $attempt($etoile, 'wave', PaymentAttempt::FAILED);
        $attempt($lagune, 'orange_money', PaymentAttempt::FAILED);
        $attempt($lagune, 'orange_money', PaymentAttempt::EXPIRED);
        $attempt($lagune, 'orange_money', PaymentAttempt::PENDING);

        Livewire::actingAs($this->admin)->test(AdminDashboard::class)
            ->assertSee('Taux de réussite')
            ->assertSee('40,0 %')
            ->assertSee('2 payés sur 5 tentatives')
            ->assertSee('Voir les paiements échoués')
            ->assertSee('Réussite par moyen de paiement')
            ->assertSee('66,7 %')
            ->assertSee('Réussite par pressing')
            ->assertSee('Pressing Lagune')
            ->assertViewHas('payments', function (array $payments) {
                $lagune = collect($payments['byPressing'])->firstWhere('name', 'Pressing Lagune');

                return $payments['online']['failed'] === 3
                    && $payments['online']['collected'] === 40600
                    && $lagune['rate'] === 0.0 && $lagune['pending'] === 1;
            });
    }

    public function test_the_payments_page_finds_a_payment_by_reference_and_filters_failures(): void
    {
        [, $request] = $this->pendingRenewal();
        $paid = PaymentAttempt::create([
            'reference' => 'PL-261029-PAYE1', 'subscription_request_id' => $request->id, 'pressing_id' => $request->pressing_id, 'pressing_name' => 'Pressing Étoile',
            'channel' => PaymentAttempt::ONLINE, 'payment_method' => 'wave', 'status' => PaymentAttempt::PAID, 'amount_fcfa' => 20000, 'total_fcfa' => 20300, 'paid_amount_fcfa' => 20300, 'started_at' => now(),
        ]);
        PaymentAttempt::create([
            'reference' => 'PL-261029-ECHEC', 'subscription_request_id' => $request->id, 'pressing_id' => $request->pressing_id, 'pressing_name' => 'Pressing Étoile',
            'channel' => PaymentAttempt::ONLINE, 'payment_method' => 'card', 'status' => PaymentAttempt::FAILED, 'failure_reason' => 'Solde insuffisant',
            'amount_fcfa' => 20000, 'total_fcfa' => 20500, 'started_at' => now(),
        ]);

        $this->actingAs($this->admin)->get(route('admin.payments.index'))->assertOk()->assertSee('PL-261029-PAYE1')->assertSee('PL-261029-ECHEC');
        $this->actingAs(User::factory()->create())->get(route('admin.payments.index'))->assertForbidden();

        Livewire::actingAs($this->admin)->test(AdminPayments::class)
            ->set('status', 'echoue')
            ->assertSee('PL-261029-ECHEC')
            ->assertSee('Solde insuffisant')
            ->assertDontSee('PL-261029-PAYE1')
            ->assertViewHas('summary', fn (array $summary) => $summary['rate'] === 50.0)
            ->set('status', '')
            ->set('search', 'PAYE1')
            ->assertSee($paid->reference)
            ->assertDontSee('PL-261029-ECHEC');
    }
}
