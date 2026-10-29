<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Livewire\Subscription\Checkout;
use App\Livewire\Subscription\Plans;
use App\Livewire\Subscription\Show as SubscriptionShow;
use App\Models\PaymentAttempt;
use App\Models\Pressing;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Paiement en ligne des demandes d'abonnement par GeniusPay : page de paiement,
 * notification signée, retour du pressing, activation une seule fois.
 */
class GeniusPayPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_presslink';

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

        // Aucun appel réel à GeniusPay pendant les tests.
        Http::preventStrayRequests();
    }

    /** @return array{0: Pressing, 1: Subscription, 2: User, 3: SubscriptionRequest} */
    private function pendingRenewal(int $amount = 20000): array
    {
        $pressing = Pressing::factory()->create();
        $subscription = Subscription::factory()->for($pressing)->active(SubscriptionPlan::Starter)->create([
            'current_period_ends_at' => now()->addDays(3),
        ]);
        $owner = User::factory()->create();
        $pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        $request = SubscriptionRequest::create([
            'pressing_id' => $pressing->id,
            'requested_by' => $owner->id,
            'kind' => SubscriptionRequest::KIND_RENEWAL,
            'plan' => SubscriptionPlan::Starter,
            'cycle' => SubscriptionRequest::MONTHLY,
            'amount_fcfa' => $amount,
            'status' => SubscriptionRequest::PENDING,
        ]);

        return [$pressing, $subscription, $owner, $request];
    }

    private function fakeCheckout(string $reference = 'SANDBOX-ABC123'): void
    {
        Http::fake([
            'geniuspay.test/api/v1/merchant/payments' => Http::response(['success' => true, 'data' => [
                'reference' => $reference,
                'checkout_url' => 'https://geniuspay.ci/checkout/'.$reference,
                'status' => 'pending',
                'environment' => 'sandbox',
            ]], 201),
        ]);
    }

    /** @param  array<string, mixed>  $transaction */
    private function postWebhook(array $transaction, string $event = 'payment.success', ?string $secret = self::SECRET, string $environment = 'sandbox'): TestResponse
    {
        $body = json_encode(['event' => $event, 'timestamp' => now()->toIso8601String(), 'data' => ['transaction' => $transaction, 'environment' => $environment]]);
        $timestamp = (string) now()->timestamp;

        return $this->call('POST', route('webhooks.geniuspay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, (string) $secret),
            'HTTP_X_WEBHOOK_ENVIRONMENT' => $environment,
        ], $body);
    }

    public function test_the_pressing_pays_online_on_the_geniuspay_page_for_the_amount_set_by_the_server(): void
    {
        [, , $owner, $request] = $this->pendingRenewal();
        $this->fakeCheckout();

        Livewire::actingAs($owner)->test(SubscriptionShow::class)
            ->assertSee(route('subscription.checkout', $request), false);

        Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request])
            ->assertSee('Récapitulatif du paiement')
            ->assertSee('Payer 20 300 F avec Wave')
            ->assertSee("Frais de l'opérateur Wave (1,5 %)", false)
            ->assertSee('Orange Money')
            ->assertSee('Carte bancaire')
            ->call('pay')
            ->assertRedirect('https://geniuspay.ci/checkout/SANDBOX-ABC123');

        Http::assertSent(fn (HttpRequest $sent) => $sent->url() === 'https://geniuspay.test/api/v1/merchant/payments'
            && $sent['amount'] === 20300
            && $sent['currency'] === 'XOF'
            && $sent['payment_method'] === 'wave'
            && $sent->hasHeader('X-API-Key', 'pk_sandbox_test')
            && $sent['metadata']['subscription_request_id'] === $request->id
            && $sent['external_reference'] === PaymentAttempt::where('subscription_request_id', $request->id)->value('reference')
            && $sent['success_url'] === route('subscription.payment.return', $request));

        $request->refresh();
        $this->assertSame('SANDBOX-ABC123', $request->payment_reference);
        $this->assertTrue($request->hasOnlinePaymentInProgress());
    }

    public function test_the_same_payment_page_is_reused_while_it_is_valid(): void
    {
        [, , $owner, $request] = $this->pendingRenewal();
        $this->fakeCheckout();

        $component = Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request]);
        $component->call('pay');
        $component->call('pay')->assertRedirect('https://geniuspay.ci/checkout/SANDBOX-ABC123');

        Http::assertSentCount(1);
    }

    public function test_without_geniuspay_keys_the_pressing_keeps_the_wave_link_flow(): void
    {
        config(['services.geniuspay.api_key' => null]);
        [, , $owner, $request] = $this->pendingRenewal();
        $request->update(['payment_link' => 'https://pay.wave.com/m/test']);

        Livewire::actingAs($owner)->test(SubscriptionShow::class)
            ->assertDontSee(route('subscription.checkout', $request), false)
            ->assertSee('Payer avec Wave');
    }

    public function test_a_signed_success_notification_activates_the_request_once(): void
    {
        [, $subscription, , $request] = $this->pendingRenewal();
        $request->update(['payment_reference' => 'SANDBOX-ABC123', 'payment_started_at' => now()]);
        $endBefore = $subscription->current_period_ends_at->copy();

        $transaction = ['reference' => 'SANDBOX-ABC123', 'amount' => 20000, 'status' => 'completed', 'payment_method' => 'wave'];
        $this->postWebhook($transaction)->assertOk();
        $this->postWebhook($transaction)->assertOk();

        $request->refresh();
        $this->assertSame(SubscriptionRequest::DONE, $request->status);
        $this->assertNull($request->handled_by);
        $this->assertNotNull($request->paid_online_at);
        $this->assertSame('wave', $request->payment_method);
        $this->assertTrue($subscription->fresh()->current_period_ends_at->equalTo($endBefore->addMonthNoOverflow()), 'prolongée une seule fois');
    }

    public function test_a_notification_with_a_bad_signature_is_refused(): void
    {
        [, , , $request] = $this->pendingRenewal();
        $request->update(['payment_reference' => 'SANDBOX-ABC123']);

        $this->postWebhook(['reference' => 'SANDBOX-ABC123', 'amount' => 20000, 'status' => 'completed'], secret: 'whsec_wrong')->assertUnauthorized();

        $this->assertTrue($request->fresh()->isPending());
    }

    public function test_a_payment_with_another_amount_or_from_the_other_environment_does_not_activate_the_request(): void
    {
        [, , , $request] = $this->pendingRenewal();
        $request->update(['payment_reference' => 'SANDBOX-ABC123']);

        $this->postWebhook(['reference' => 'SANDBOX-ABC123', 'amount' => 200, 'status' => 'completed'])->assertOk();
        $this->assertTrue($request->fresh()->isPending());

        $this->postWebhook(['reference' => 'SANDBOX-ABC123', 'amount' => 20000, 'status' => 'completed'], environment: 'live')->assertOk();
        $this->assertTrue($request->fresh()->isPending());
        $this->assertNull($request->fresh()->paid_online_at);
    }

    public function test_back_from_the_payment_page_the_request_is_checked_and_activated(): void
    {
        [, , $owner, $request] = $this->pendingRenewal();
        $request->update(['payment_reference' => 'SANDBOX-ABC123', 'payment_started_at' => now()]);
        Http::fake([
            'geniuspay.test/api/v1/merchant/payments/SANDBOX-ABC123' => Http::response(['success' => true, 'data' => [
                'reference' => 'SANDBOX-ABC123', 'amount' => 20000, 'status' => 'completed', 'payment_method' => 'orange_money',
            ]]),
        ]);

        $this->actingAs($owner)->get(route('subscription.payment.return', $request))
            ->assertRedirect(route('subscription.show'))
            ->assertSessionHas('toast', fn (array $toast) => $toast['type'] === 'success');

        $this->assertSame(SubscriptionRequest::DONE, $request->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $request->pressing->subscription->fresh()->status);
    }

    public function test_another_pressing_cannot_use_the_return_page_of_a_request(): void
    {
        [, , , $request] = $this->pendingRenewal();
        [, , $otherOwner] = $this->pendingRenewal();

        $this->actingAs($otherOwner)->get(route('subscription.payment.return', $request))->assertForbidden();
    }

    public function test_a_request_cannot_be_cancelled_while_its_online_payment_is_in_progress(): void
    {
        [, , $owner, $request] = $this->pendingRenewal();
        $request->update(['payment_reference' => 'SANDBOX-ABC123', 'payment_started_at' => now()]);

        Livewire::actingAs($owner)->test(SubscriptionShow::class)->call('cancelRequest', $request->id);
        Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request])->call('cancel');

        $this->assertNotNull($request->fresh());
    }

    public function test_choosing_a_higher_plan_goes_straight_to_the_payment_summary(): void
    {
        Mail::fake();
        [$pressing, , $owner] = $this->pendingRenewal();

        Livewire::actingAs($owner)->test(Plans::class)
            ->call('requestPlan', SubscriptionPlan::Business->value, SubscriptionRequest::MONTHLY)
            ->assertRedirect(route('subscription.checkout', $pressing->subscriptionRequests()->where('kind', SubscriptionRequest::KIND_PLAN)->firstOrFail()));

        Mail::assertNothingSent();
    }

    public function test_choosing_the_same_plan_again_reuses_the_request_and_follows_the_new_cycle(): void
    {
        [$pressing, , $owner] = $this->pendingRenewal();
        $component = Livewire::actingAs($owner)->test(Plans::class);

        $component->call('requestPlan', SubscriptionPlan::Business->value, SubscriptionRequest::MONTHLY);
        $component->call('requestPlan', SubscriptionPlan::Business->value, SubscriptionRequest::ANNUAL);

        $requests = $pressing->subscriptionRequests()->where('kind', SubscriptionRequest::KIND_PLAN)->get();
        $this->assertCount(1, $requests);
        $this->assertSame(SubscriptionRequest::ANNUAL, $requests->first()->cycle);
        $this->assertSame(SubscriptionRequest::planChangePrice($pressing->subscription, SubscriptionPlan::Business, SubscriptionRequest::ANNUAL)['amount'], $requests->first()->amount_fcfa);
    }

    public function test_a_pressing_in_free_trial_keeps_its_free_days_when_it_pays(): void
    {
        $pressing = Pressing::factory()->create();
        $subscription = Subscription::factory()->for($pressing)->create([
            'plan' => SubscriptionPlan::Starter,
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(10)->endOfDay(),
        ]);
        $owner = User::factory()->create();
        $pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        Livewire::actingAs($owner)->test(Plans::class)->call('requestPlan', SubscriptionPlan::Business->value, SubscriptionRequest::MONTHLY);
        $request = $pressing->subscriptionRequests()->firstOrFail();
        $trialEnd = $subscription->trial_ends_at->copy();

        Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request])
            ->assertSee('Votre essai gratuit continue jusqu')
            ->assertSee($trialEnd->format('d/m/Y'))
            ->assertSee($trialEnd->copy()->addMonthNoOverflow()->format('d/m/Y'));

        $request->confirmOnlinePayment(['status' => 'completed', 'amount' => $request->amount_fcfa]);

        $subscription->refresh();
        $this->assertSame(SubscriptionPlan::Business, $subscription->plan);
        $this->assertTrue($subscription->current_period_starts_at->equalTo($trialEnd));
        $this->assertTrue($subscription->current_period_ends_at->equalTo($trialEnd->copy()->addMonthNoOverflow()));
    }

    public function test_an_unconfirmed_payment_brings_the_pressing_back_to_the_summary(): void
    {
        [, , $owner, $request] = $this->pendingRenewal();
        $request->update(['payment_reference' => 'SANDBOX-ABC123', 'payment_started_at' => now()]);
        Http::fake([
            'geniuspay.test/api/v1/merchant/payments/SANDBOX-ABC123' => Http::response(['success' => true, 'data' => [
                'reference' => 'SANDBOX-ABC123', 'amount' => 20000, 'status' => 'failed',
            ]]),
        ]);

        $this->actingAs($owner)->get(route('subscription.payment.return', $request))
            ->assertRedirect(route('subscription.checkout', ['subscriptionRequest' => $request, 'echec' => 1]));

        $this->assertTrue($request->fresh()->isPending());
        $this->assertNull($request->fresh()->payment_reference, 'un paiement refusé ne se reprend pas : le prochain « Payer » en ouvre un nouveau');
        $this->assertFalse($request->fresh()->hasOnlinePaymentInProgress());
    }

    public function test_a_failed_payment_notification_frees_the_request_for_a_new_payment(): void
    {
        [, , , $request] = $this->pendingRenewal();
        $request->update(['payment_reference' => 'SANDBOX-ABC123', 'payment_checkout_url' => 'https://geniuspay.ci/checkout/SANDBOX-ABC123', 'payment_started_at' => now()]);

        $this->postWebhook(['reference' => 'SANDBOX-ABC123', 'amount' => 20000, 'status' => 'failed'], event: 'payment.failed')->assertOk();

        $this->assertNull($request->fresh()->payment_checkout_url);
        $this->assertTrue($request->fresh()->isPending());
    }

    public function test_another_pressing_cannot_open_the_payment_summary(): void
    {
        [, , , $request] = $this->pendingRenewal();
        [, , $otherOwner] = $this->pendingRenewal();

        $this->actingAs($otherOwner)->get(route('subscription.checkout', $request))->assertForbidden();
    }

    /** @return array{0: Pressing, 1: Subscription, 2: User} Business payé au mois, à mi-période (15 jours faits, 15 restants). */
    private function businessHalfway(): array
    {
        $this->travelTo('2026-11-20 10:00:00');
        $pressing = Pressing::factory()->create();
        $subscription = Subscription::factory()->for($pressing)->active(SubscriptionPlan::Business)->create([
            'billing_cycle' => SubscriptionRequest::MONTHLY,
            'current_period_starts_at' => now()->subDays(15),
            'current_period_ends_at' => now()->addDays(15),
        ]);
        $owner = User::factory()->create();
        $pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return [$pressing, $subscription, $owner];
    }

    public function test_an_upgrade_deducts_the_unused_paid_days_and_starts_today(): void
    {
        [$pressing, $subscription, $owner] = $this->businessHalfway();
        // 15 jours restants au tarif journalier de Business (30 000 F ÷ 365/12 jours), à la centaine inférieure.
        $credit = (int) (floor(SubscriptionPlan::Business->monthlyPriceFcfa() * 15 / (365 / 12) / 100) * 100);
        $expected = SubscriptionPlan::Premium->monthlyPriceFcfa() - $credit;

        Livewire::actingAs($owner)->test(Plans::class)->call('requestPlan', SubscriptionPlan::Premium->value, SubscriptionRequest::MONTHLY);
        $request = $pressing->subscriptionRequests()->firstOrFail();
        $this->assertSame($expected, $request->amount_fcfa);

        Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request])
            ->assertSee('Jours Business non utilisés')
            ->assertSee('− '.number_format($credit, 0, ',', ' ').' F')
            ->assertSee('Payer '.number_format($expected + SubscriptionRequest::paymentFeeFor($expected, 'wave'), 0, ',', ' ').' F avec Wave');

        $request->confirmOnlinePayment(['status' => 'completed', 'amount' => $expected]);

        $subscription->refresh();
        $this->assertSame(SubscriptionPlan::Premium, $subscription->plan);
        $this->assertTrue($subscription->current_period_starts_at->equalTo(now()));
        $this->assertTrue($subscription->current_period_ends_at->equalTo(now()->addMonthNoOverflow()));
    }

    public function test_a_downgrade_keeps_every_paid_day_and_starts_after_the_paid_period(): void
    {
        [$pressing, $subscription, $owner] = $this->businessHalfway();
        $paidUntil = $subscription->current_period_ends_at->copy();

        Livewire::actingAs($owner)->test(Plans::class)->call('requestPlan', SubscriptionPlan::Starter->value, SubscriptionRequest::MONTHLY);
        $request = $pressing->subscriptionRequests()->firstOrFail();
        $this->assertSame(SubscriptionPlan::Starter->monthlyPriceFcfa(), $request->amount_fcfa, 'prix plein, pas de déduction');

        Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request])
            ->assertSee('Vous ne perdez aucun jour payé')
            ->assertSee($paidUntil->format('d/m/Y'));

        $request->confirmOnlinePayment(['status' => 'completed', 'amount' => $request->amount_fcfa]);

        $subscription->refresh();
        $this->assertTrue($subscription->current_period_starts_at->equalTo($paidUntil));
        $this->assertTrue($subscription->current_period_ends_at->equalTo($paidUntil->copy()->addMonthNoOverflow()));
    }

    public function test_the_deduction_is_updated_when_the_pressing_comes_back_later(): void
    {
        [$pressing, , $owner] = $this->businessHalfway();
        Livewire::actingAs($owner)->test(Plans::class)->call('requestPlan', SubscriptionPlan::Premium->value, SubscriptionRequest::MONTHLY);
        $request = $pressing->subscriptionRequests()->firstOrFail();
        $before = $request->amount_fcfa;

        $this->travel(5)->days();
        Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request]);

        $this->assertGreaterThan($before, $request->fresh()->amount_fcfa, 'moins de jours restants, moins de déduction');
    }

    public function test_orange_money_asks_for_the_paying_number_and_sends_it_to_geniuspay(): void
    {
        [, , $owner, $request] = $this->pendingRenewal();
        $this->fakeCheckout();

        Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request])
            ->set('method', 'orange_money')
            ->assertSee('Numéro Orange Money qui paie')
            ->set('phone', '')
            ->call('pay')
            ->assertHasErrors('phone')
            ->set('phone', '07 08 12 44 00')
            ->call('pay')
            ->assertHasNoErrors()
            ->assertRedirect('https://geniuspay.ci/checkout/SANDBOX-ABC123');

        Http::assertSent(fn (HttpRequest $sent) => $sent['payment_method'] === 'orange_money' && $sent['customer']['phone'] === '+2250708124400');
        $this->assertSame('orange_money', $request->fresh()->payment_method);
    }

    public function test_the_operator_page_is_used_when_geniuspay_returns_one(): void
    {
        [, , $owner, $request] = $this->pendingRenewal();
        Http::fake([
            'geniuspay.test/api/v1/merchant/payments' => Http::response(['success' => true, 'data' => [
                'reference' => 'MTX-WAVE1', 'checkout_url' => 'https://geniuspay.ci/checkout/MTX-WAVE1', 'payment_url' => 'https://pay.wave.com/c/abc',
            ]], 201),
        ]);

        Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request])
            ->call('pay')
            ->assertRedirect('https://pay.wave.com/c/abc');
    }

    public function test_choosing_another_method_opens_a_new_payment(): void
    {
        [, , $owner, $request] = $this->pendingRenewal();
        $this->fakeCheckout();

        $component = Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request]);
        $component->call('pay');
        $component->set('method', 'card')->call('pay');

        Http::assertSentCount(2);
        $this->assertSame('card', $request->fresh()->payment_method);
    }

    public function test_the_operator_fee_follows_the_rate_set_by_the_super_admin(): void
    {
        [, , $owner, $request] = $this->pendingRenewal();
        Setting::write('payment_fee_rates', ['wave' => 1, 'orange_money' => 0]);

        Livewire::actingAs($owner)->test(Checkout::class, ['subscriptionRequest' => $request])
            ->assertSee('+ 200 F')
            ->assertSee('Payer 20 200 F avec Wave')
            ->set('method', 'orange_money')
            ->assertDontSee("Frais de l'opérateur", false)
            ->assertSee('Payer 20 000 F avec Orange Money');
    }

    public function test_the_fees_kept_by_geniuspay_and_the_net_received_are_recorded(): void
    {
        [, , , $request] = $this->pendingRenewal();
        $request->update(['payment_reference' => 'SANDBOX-ABC123', 'payment_started_at' => now(), 'payment_fee_fcfa' => 300]);

        $this->postWebhook(['reference' => 'SANDBOX-ABC123', 'amount' => 20300, 'status' => 'completed', 'fees' => 603, 'net_amount' => 19697])->assertOk();

        $request->refresh();
        $this->assertSame(SubscriptionRequest::DONE, $request->status);
        $this->assertSame(20300, $request->paid_amount_fcfa);
        $this->assertSame(300, $request->payment_fee_fcfa);
        $this->assertSame(603, $request->gateway_fees_fcfa);
        $this->assertSame(19697, $request->net_received_fcfa);
    }

    public function test_missing_fees_are_asked_to_geniuspay(): void
    {
        [, , , $request] = $this->pendingRenewal();
        $request->update(['payment_reference' => 'SANDBOX-ABC123', 'payment_started_at' => now()]);
        Http::fake([
            'geniuspay.test/api/v1/merchant/payments/SANDBOX-ABC123' => Http::response(['success' => true, 'data' => [
                'reference' => 'SANDBOX-ABC123', 'amount' => 20300, 'status' => 'completed', 'fees' => 603, 'net_amount' => 19697,
            ]]),
        ]);

        $this->postWebhook(['reference' => 'SANDBOX-ABC123', 'amount' => 20300, 'status' => 'completed'])->assertOk();

        $this->assertSame(19697, $request->fresh()->net_received_fcfa);
    }

    public function test_a_signed_in_pressing_chooses_its_plan_in_its_space_from_the_public_pricing_page(): void
    {
        [, , $owner] = $this->pendingRenewal();

        $this->get(route('site.pricing'))->assertOk()->assertDontSee('data-testid="choose-in-space"', false);

        $this->actingAs($owner)->get(route('site.pricing'))->assertOk()
            ->assertSee('data-testid="choose-in-space"', false)
            ->assertSee(route('subscription.plans'), false);
    }
}
