<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PaymentMethod;
use App\Enums\PressingRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\User;
use App\Support\ReceiptLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reçus de dépôt et de paiement : téléchargeables (PDF) et partageables par
 * un lien signé, qui s'ouvre sans connexion — y compris depuis l'appli client.
 */
class ReceiptSharingTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private Customer $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);
        $this->order = (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => null, 'name' => 'Costume', 'unit_price_fcfa' => 10000, 'quantity' => 1],
        ], amountPaidFcfa: 4000);
        $this->order->recordPayment(2500, PaymentMethod::Wave);
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $this->pressing->staff()->attach($user, ['role' => PressingRole::Employee->value, 'is_active' => true]);

        return $user;
    }

    public function test_staff_open_and_download_a_payment_receipt(): void
    {
        $payment = $this->order->payments()->reorder('id', 'desc')->firstOrFail();
        $this->actingAs($this->staff());

        $this->get(route('orders.payment-receipt', ['order' => $this->order, 'payment' => $payment]))
            ->assertOk()
            ->assertSee('Reçu de paiement')
            ->assertSee($this->order->order_number.'-2')
            ->assertSee('2 500 F', false)
            ->assertSee('6 500 F', false)   // payé à ce jour
            ->assertSee('3 500 F', false)   // reste à payer
            ->assertSee('Partager');

        $this->get(route('orders.payment-receipt', ['order' => $this->order, 'payment' => $payment, 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_payment_of_another_order_is_refused(): void
    {
        $other = (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => null, 'name' => 'Robe', 'unit_price_fcfa' => 3000, 'quantity' => 1],
        ], amountPaidFcfa: 3000);
        $this->actingAs($this->staff());

        $this->get(route('orders.payment-receipt', ['order' => $this->order, 'payment' => $other->payments()->firstOrFail()]))
            ->assertForbidden();
    }

    public function test_shared_links_open_without_login_only_when_signed(): void
    {
        $this->get(ReceiptLinks::order($this->order))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(ReceiptLinks::payment($this->order->payments()->firstOrFail(), download: true))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename='.ReceiptLinks::paymentFileName($this->order->payments()->firstOrFail()));

        $this->get('/recus/commande/'.$this->order->id)->assertForbidden();
        $this->get(ReceiptLinks::order($this->order).'x')->assertForbidden();
    }

    public function test_the_customer_app_gets_its_receipt_links(): void
    {
        $token = $this->customer->createToken('app')->plainTextToken;

        $json = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/orders/{$this->order->id}")
            ->assertOk()
            ->assertJsonPath('receipt.file_name', "recu-{$this->order->order_number}.pdf");

        $this->get($json->json('receipt.download_url'))->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
