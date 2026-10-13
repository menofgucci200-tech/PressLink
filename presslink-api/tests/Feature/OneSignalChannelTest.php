<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneSignalChannelTest extends TestCase
{
    use RefreshDatabase;

    private function readyOrder(Customer $customer): void
    {
        $order = Order::factory()->for($customer)->create();
        $order->update(['status' => OrderStatus::Traitement]);
        $order->update(['status' => OrderStatus::Prete]);
    }

    public function test_push_is_sent_to_the_customer_subscription_with_the_app_api_key(): void
    {
        config(['services.onesignal.app_id' => 'app-id', 'services.onesignal.rest_api_key' => 'os_v2_app_test']);
        Http::fake(['api.onesignal.com/*' => Http::response(['id' => 'n1'])]);
        $customer = Customer::factory()->create(['onesignal_player_id' => 'sub-123']);

        $this->readyOrder($customer);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.onesignal.com/notifications'
            && $request->header('Authorization')[0] === 'Key os_v2_app_test'
            && $request['include_subscription_ids'] === ['sub-123']
            && $request['web_url'] === url('/app/'));
    }

    public function test_unreachable_onesignal_never_breaks_the_status_change(): void
    {
        config(['services.onesignal.app_id' => 'app-id', 'services.onesignal.rest_api_key' => 'os_v2_app_test']);
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $customer = Customer::factory()->create(['onesignal_player_id' => 'sub-123']);

        $this->readyOrder($customer);

        $this->assertSame(OrderStatus::Prete, $customer->orders()->first()->status);
    }
}
