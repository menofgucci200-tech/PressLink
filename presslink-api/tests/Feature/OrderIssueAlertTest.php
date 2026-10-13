<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PressingRole;
use App\Mail\OrderIssueReported;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Alerte email aux administrateurs du pressing quand un client signale un
 * problème sur une commande.
 */
class OrderIssueAlertTest extends TestCase
{
    use RefreshDatabase;

    private function reportIssue(Pressing $pressing): void
    {
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$customer->createToken('test')->plainTextToken)
            ->postJson("/api/v1/orders/{$order->id}/issues", [
                'category' => 'missing_item',
                'description' => 'Il manque une chemise.',
            ])->assertCreated();
    }

    public function test_active_pressing_admins_are_alerted_by_email(): void
    {
        Mail::fake();
        $pressing = Pressing::factory()->create();
        $admin = User::factory()->create(['email' => 'gerant@pressing.test']);
        $inactiveAdmin = User::factory()->create(['email' => 'ancien@pressing.test']);
        $employee = User::factory()->create(['email' => 'employe@pressing.test']);
        $pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $pressing->staff()->attach($inactiveAdmin, ['role' => PressingRole::Admin->value, 'is_active' => false]);
        $pressing->staff()->attach($employee, ['role' => PressingRole::Employee->value, 'is_active' => true]);

        $this->reportIssue($pressing);

        Mail::assertSent(OrderIssueReported::class, fn (OrderIssueReported $mail) => $mail->hasTo('gerant@pressing.test')
            && ! $mail->hasTo('ancien@pressing.test')
            && ! $mail->hasTo('employe@pressing.test'));
    }

    public function test_alert_email_describes_the_issue(): void
    {
        Mail::fake();
        $pressing = Pressing::factory()->create();
        $admin = User::factory()->create(['email' => 'gerant@pressing.test']);
        $pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        $this->reportIssue($pressing);

        Mail::assertSent(OrderIssueReported::class, function (OrderIssueReported $mail) {
            $mail->assertSeeInHtml('Il manque un article');
            $mail->assertSeeInHtml('Il manque une chemise.');
            $mail->assertSeeInHtml($mail->issue->order->order_number);

            return str_starts_with($mail->envelope()->subject, 'Signalement sur la commande');
        });
    }

    public function test_no_email_when_the_pressing_has_no_admin_email(): void
    {
        Mail::fake();

        $this->reportIssue(Pressing::factory()->create());

        Mail::assertNothingSent();
    }
}
