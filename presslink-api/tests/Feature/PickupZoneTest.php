<?php

namespace Tests\Feature;

use App\Actions\Pickups\PickupRequests;
use App\Enums\PickupRequestStatus;
use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use App\Enums\SubscriptionPlan;
use App\Livewire\Pickups\Index;
use App\Mail\PickupRequestAlert;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\PickupRequest;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PickupAcceptedNotification;
use App\Notifications\PickupExpiredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ramassage ouvert à la zone : le client ne dépend pas d'un pressing. Il voit
 * les pressings qui passent dans sa commune, en choisit un ou laisse sa
 * demande au premier pressing de la zone qui l'accepte.
 */
class PickupZoneTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $elegance;

    private Pressing $lavoir;

    private Customer $customer;

    private CustomerAddress $address;

    protected function setUp(): void
    {
        parent::setUp();

        config(['presslink.delivery.launched' => true]);
        // Lundi 16 novembre 2026, 9 h.
        $this->travelTo(Carbon::parse('2026-11-16 09:00'));

        $this->elegance = $this->pickupPressing('Pressing Élégance', 1000, ['Cocody', 'Plateau']);
        $this->lavoir = $this->pickupPressing('Lavoir Riviera', 500, ['Cocody']);

        // Le client n'est client d'aucun pressing.
        $this->customer = Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné']);
        $this->address = CustomerAddress::factory()->for($this->customer)->create(['commune' => 'Cocody', 'neighborhood' => 'Riviera 3', 'landmark' => 'Portail vert face au maquis']);

        $this->withHeader('Authorization', 'Bearer '.$this->customer->createToken('test')->plainTextToken);
    }

    /** @param  list<string>  $communes */
    private function pickupPressing(string $name, int $fee, array $communes): Pressing
    {
        $pressing = Pressing::factory()->create(['name' => $name, 'opening_hours' => null]);
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Business)->create();
        $pressing->update(['pickup_enabled' => true, 'pickup_fee_fcfa' => $fee]);
        foreach ($communes as $commune) {
            $pressing->serviceAreas()->create(['commune' => $commune]);
        }

        return $pressing;
    }

    private function ownerOf(Pressing $pressing, string $email): User
    {
        $owner = User::factory()->create(['email' => $email]);
        $pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return $owner;
    }

    /** @return array<string, mixed> */
    private function zonePayload(array $overrides = []): array
    {
        return [
            'address_id' => $this->address->id,
            'pickup_date' => '2026-11-17',
            'slot_from' => '08:00',
            'slot_to' => '12:00',
            'items_estimate' => 8,
            ...$overrides,
        ];
    }

    private function zoneRequest(): PickupRequest
    {
        $this->postJson('/api/v1/pickup-requests', $this->zonePayload())->assertCreated();

        return PickupRequest::sole();
    }

    public function test_the_customer_sees_the_pressings_of_their_commune_and_the_zone_option(): void
    {
        // Ni en Abobo, ni suspendu, ni sans le ramassage dans sa formule.
        $this->pickupPressing('Pressing Abobo', 300, ['Abobo']);
        $this->pickupPressing('Pressing Suspendu', 300, ['Cocody'])->update(['status' => PressingStatus::Suspended]);
        $starter = Pressing::factory()->create(['pickup_enabled' => true]);
        Subscription::factory()->for($starter)->active(SubscriptionPlan::Starter)->create();
        $starter->serviceAreas()->create(['commune' => 'Cocody']);

        $this->lavoir->customers()->attach($this->customer, ['joined_at' => now()]);

        $response = $this->getJson("/api/v1/pickup-options?address_id={$this->address->id}")->assertOk()
            ->assertJsonPath('commune', 'Cocody')
            ->assertJsonCount(2, 'pressings')
            // Ses pressings d'abord, puis par nom.
            ->assertJsonPath('pressings.0.name', 'Lavoir Riviera')
            ->assertJsonPath('pressings.0.is_mine', true)
            ->assertJsonPath('pressings.1.name', 'Pressing Élégance')
            ->assertJsonPath('pressings.1.is_mine', false)
            ->assertJsonPath('pressings.1.pickup_fee_fcfa', 1000)
            ->assertJsonPath('zone.fee_min_fcfa', 500)
            ->assertJsonPath('zone.fee_max_fcfa', 1000);

        $this->assertSame('Demain', $response->json('zone.days.1.label'));
        $this->assertSame(Pressing::DEFAULT_DELIVERY_SLOTS, $response->json('zone.days.1.slots'));

        $elsewhere = CustomerAddress::factory()->for($this->customer)->create(['commune' => 'Yopougon']);
        $this->getJson("/api/v1/pickup-options?address_id={$elsewhere->id}")->assertOk()
            ->assertJsonCount(0, 'pressings')
            ->assertJsonPath('zone', null);

        $someoneElses = CustomerAddress::factory()->create();
        $this->getJson("/api/v1/pickup-options?address_id={$someoneElses->id}")->assertNotFound();
    }

    public function test_a_request_left_to_the_zone_alerts_every_pressing_of_the_commune_without_the_customers_contact(): void
    {
        Mail::fake();
        $this->ownerOf($this->elegance, 'elegance@pressing.test');
        $this->ownerOf($this->lavoir, 'lavoir@pressing.test');
        $plateau = $this->pickupPressing('Pressing Plateau', 800, ['Plateau']);
        $this->ownerOf($plateau, 'plateau@pressing.test');

        $this->postJson('/api/v1/pickup-requests', $this->zonePayload())->assertCreated()
            ->assertJsonPath('open_to_zone', true)
            ->assertJsonPath('pressing', null)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('pickup_fee_fcfa', 0);

        $request = PickupRequest::sole();
        $this->assertNull($request->pressing_id);

        Mail::assertSent(PickupRequestAlert::class, 2);
        Mail::assertSent(PickupRequestAlert::class, fn (PickupRequestAlert $mail) => $mail->hasTo('elegance@pressing.test')
            && $mail->envelope()->subject === 'Ramassage à prendre à Cocody — demain, 08:00 – 12:00'
            && str_contains($mail->render(), 'le premier qui l\'accepte la prend')
            && ! str_contains($mail->render(), 'Koné')
            && ! str_contains($mail->render(), 'Portail vert'));
        Mail::assertNotSent(PickupRequestAlert::class, fn (PickupRequestAlert $mail) => $mail->hasTo('plateau@pressing.test'));

        // Une seule demande ouverte à la zone à la fois.
        $this->postJson('/api/v1/pickup-requests', $this->zonePayload())->assertUnprocessable()
            ->assertJsonPath('message', 'Vous avez déjà une demande en attente d\'un pressing de votre zone.');
    }

    public function test_the_zone_needs_a_pressing_and_a_slot_one_of_them_offers(): void
    {
        $elsewhere = CustomerAddress::factory()->for($this->customer)->create(['commune' => 'Yopougon']);
        $this->postJson('/api/v1/pickup-requests', $this->zonePayload(['address_id' => $elsewhere->id]))
            ->assertUnprocessable()->assertJsonPath('message', 'Aucun pressing ne passe encore à Yopougon.');

        $this->postJson('/api/v1/pickup-requests', $this->zonePayload(['slot_from' => '19:00', 'slot_to' => '21:00']))
            ->assertUnprocessable()->assertJsonPath('message', 'Ce créneau n\'est plus disponible. Choisissez-en un autre.');

        // Le créneau d'un seul des pressings suffit.
        $this->lavoir->update(['delivery_slots' => [['from' => '18:00', 'to' => '20:00']]]);
        $this->postJson('/api/v1/pickup-requests', $this->zonePayload(['slot_from' => '18:00', 'slot_to' => '20:00']))->assertCreated();
    }

    public function test_the_first_pressing_to_accept_takes_the_request_and_the_customer_becomes_its_customer(): void
    {
        Notification::fake();
        $request = $this->zoneRequest();
        $first = $this->ownerOf($this->lavoir, 'lavoir@pressing.test');

        app(PickupRequests::class)->accept($request, $first, $this->lavoir);

        $request->refresh();
        $this->assertSame($this->lavoir->id, $request->pressing_id);
        $this->assertSame(PickupRequestStatus::Accepted, $request->status);
        $this->assertSame(500, $request->pickup_fee_fcfa);
        $this->assertTrue($this->customer->pressings()->whereKey($this->lavoir->id)->exists());
        Notification::assertSentTo($this->customer, PickupAcceptedNotification::class,
            fn (PickupAcceptedNotification $notification) => $notification->body() === 'Lavoir Riviera passera chercher votre linge demain, 08:00 – 12:00. Frais de ramassage : 500 F.');

        // Trop tard pour l'autre pressing de la zone.
        $second = $this->ownerOf($this->elegance, 'elegance@pressing.test');
        try {
            app(PickupRequests::class)->accept($request, $second, $this->elegance);
            $this->fail('Le second pressing ne doit pas pouvoir prendre la demande.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Un autre pressing a déjà pris cette demande.', $e->getMessage());
        }

        $this->getJson("/api/v1/pickup-requests/{$request->id}")->assertOk()
            ->assertJsonPath('open_to_zone', false)
            ->assertJsonPath('pressing.name', 'Lavoir Riviera');
        $this->getJson('/api/v1/pressings/mine')->assertOk()->assertJsonFragment(['name' => 'Lavoir Riviera']);
    }

    public function test_the_dashboard_shows_zone_requests_without_the_contact_until_accepted(): void
    {
        Notification::fake();
        $request = $this->zoneRequest();
        $owner = $this->ownerOf($this->elegance, 'elegance@pressing.test');
        $this->actingAs($owner);

        $this->get(route('dashboard'))->assertOk()->assertSeeInOrder(['data-testid="pickups-badge"', '1'], false);

        Livewire::test(Index::class)
            ->assertSee('Ouverte aux pressings de Cocody')
            ->assertSee('Awa')
            ->assertDontSee('Koné')
            ->assertDontSee('Portail vert')
            ->assertSee('Frais de ramassage (les vôtres) : 1 000 F')
            ->call('startAccept', $request->id)
            ->call('confirmAccept')
            ->assertHasNoErrors()
            ->set('tab', 'accepted')
            ->assertSee('Awa Koné')
            ->assertSee('Portail vert face au maquis');

        $this->assertSame($this->elegance->id, $request->fresh()->pressing_id);

        // Elle a disparu chez l'autre pressing de la zone.
        $this->actingAs($this->ownerOf($this->lavoir, 'lavoir@pressing.test'));
        Livewire::test(Index::class)->assertDontSee('Awa')->assertSee('Aucune demande à traiter');
    }

    public function test_a_pressing_can_leave_a_zone_request_to_the_others(): void
    {
        $request = $this->zoneRequest();
        $this->actingAs($this->ownerOf($this->elegance, 'elegance@pressing.test'));

        Livewire::test(Index::class)
            ->assertSee('Pas pour nous')
            ->assertDontSee('Refuser')
            ->call('dismiss', $request->id)
            ->assertSee('Aucune demande à traiter');

        $this->assertSame(PickupRequestStatus::Pending, $request->fresh()->status);

        $this->actingAs($this->ownerOf($this->lavoir, 'lavoir@pressing.test'));
        Livewire::test(Index::class)->assertSee('Ouverte aux pressings de Cocody');
    }

    public function test_a_pressing_outside_the_commune_never_sees_the_request(): void
    {
        $request = $this->zoneRequest();
        $plateau = $this->pickupPressing('Pressing Plateau', 800, ['Plateau']);
        $owner = $this->ownerOf($plateau, 'plateau@pressing.test');
        $this->actingAs($owner);

        Livewire::test(Index::class)->assertDontSee('Awa');
        $this->assertSame(0, PickupRequest::visibleTo($plateau)->count());

        $this->expectExceptionMessage('Votre pressing ne passe pas à Cocody.');
        app(PickupRequests::class)->accept($request, $owner, $plateau);
    }

    public function test_the_demo_pressing_is_offered_only_to_the_customers_who_follow_it(): void
    {
        Notification::fake();
        Mail::fake();
        // Seule la démo passe à Yopougon.
        $demo = $this->pickupPressing('Pressing Démo', 700, ['Yopougon']);
        $demo->update(['is_demo' => true]);
        $yopougon = CustomerAddress::factory()->for($this->customer)->create(['commune' => 'Yopougon']);
        $payload = $this->zonePayload(['address_id' => $yopougon->id, 'pressing_id' => $demo->id]);

        $this->getJson("/api/v1/pickup-options?address_id={$yopougon->id}")->assertOk()
            ->assertJsonCount(0, 'pressings');
        $this->postJson('/api/v1/pickup-requests', $payload)->assertUnprocessable();

        // Le client a saisi le code de la démo : elle lui est proposée, sans ouvrir la zone.
        $demo->customers()->attach($this->customer, ['joined_at' => now()]);
        $this->getJson("/api/v1/pickup-options?address_id={$yopougon->id}")->assertOk()
            ->assertJsonCount(1, 'pressings')
            ->assertJsonPath('pressings.0.name', 'Pressing Démo')
            ->assertJsonPath('pressings.0.is_mine', true)
            ->assertJsonMissingPath('pressings.0.is_demo')
            ->assertJsonPath('zone', null);

        $this->postJson('/api/v1/pickup-requests', $payload)->assertCreated();
        $this->assertSame($demo->id, PickupRequest::sole()->pressing_id);
    }

    public function test_demo_and_test_pressings_never_see_nor_take_requests_left_to_the_zone(): void
    {
        $request = $this->zoneRequest();
        $demo = $this->pickupPressing('Pressing Démo', 700, ['Cocody']);
        $demo->update(['is_demo' => true]);
        $owner = $this->ownerOf($demo, 'demo@pressing.test');

        $this->assertSame(0, PickupRequest::visibleTo($demo)->count());

        $this->expectExceptionMessage('Votre pressing ne passe pas à Cocody.');
        app(PickupRequests::class)->accept($request, $owner, $demo);
    }

    public function test_an_unanswered_zone_request_expires_with_a_message_about_the_zone(): void
    {
        Notification::fake();
        $request = $this->zoneRequest();

        $this->travelTo(Carbon::parse('2026-11-17 12:05'));
        $this->assertSame(1, app(PickupRequests::class)->expireOverdue());

        $this->assertSame(PickupRequestStatus::Expired, $request->fresh()->status);
        Notification::assertSentTo($this->customer, PickupExpiredNotification::class,
            fn (PickupExpiredNotification $notification) => str_starts_with($notification->body(), 'Aucun pressing de Cocody n\'a pris votre demande'));
    }

    public function test_photos_of_a_zone_request_and_cancellation(): void
    {
        Storage::fake('local');
        $request = $this->zoneRequest();

        $this->post("/api/v1/pickup-requests/{$request->id}/photos", ['photo' => UploadedFile::fake()->image('linge.jpg', 800, 600)])->assertCreated();
        $this->assertStringStartsWith('pickup-photos/zone/'.$request->id.'/', $request->photos()->sole()->path);

        $this->postJson("/api/v1/pickup-requests/{$request->id}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
    }
}
