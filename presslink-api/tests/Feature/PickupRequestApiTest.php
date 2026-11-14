<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\PickupRequestStatus;
use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Mail\PickupRequestAlert;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\PickupRequest;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PickupExpiredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ramassage et livraison, lot 2 (côté appli client) : le client demande à
 * l'un de ses pressings de passer chercher son linge, joint des photos,
 * suit et annule sa demande.
 */
class PickupRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private Customer $customer;

    private CustomerAddress $address;

    protected function setUp(): void
    {
        parent::setUp();

        config(['presslink.delivery.launched' => true]);
        // Lundi 16 novembre 2026, 9 h.
        $this->travelTo(Carbon::parse('2026-11-16 09:00'));

        $this->pressing = Pressing::factory()->create(['name' => 'Pressing Élégance', 'opening_hours' => null]);
        Subscription::factory()->for($this->pressing)->active(SubscriptionPlan::Business)->create();
        $this->pressing->update(['pickup_enabled' => true, 'pickup_fee_fcfa' => 1000]);
        $this->pressing->serviceAreas()->create(['commune' => 'Cocody']);

        $this->customer = Customer::factory()->create();
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);
        $this->address = CustomerAddress::factory()->for($this->customer)->create(['commune' => 'Cocody', 'neighborhood' => 'Riviera 3', 'is_default' => true]);

        $this->withHeader('Authorization', 'Bearer '.$this->customer->createToken('test')->plainTextToken);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'pressing_id' => $this->pressing->id,
            'address_id' => $this->address->id,
            'pickup_date' => '2026-11-17',
            'slot_from' => '08:00',
            'slot_to' => '12:00',
            'items_estimate' => 12,
            'note' => 'Sonnez deux fois',
            ...$overrides,
        ];
    }

    private function adminWithEmail(): User
    {
        $admin = User::factory()->create(['email' => 'gerant@pressing.test']);
        $this->pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return $admin;
    }

    public function test_the_customer_sees_the_open_days_and_slots_still_bookable(): void
    {
        $this->pressing->update(['opening_hours' => [
            'lundi' => ['closed' => false, 'open' => '07:00', 'close' => '19:00'],
            'mardi' => ['closed' => false, 'open' => '07:00', 'close' => '19:00'],
            'mercredi' => ['closed' => false, 'open' => '07:00', 'close' => '19:00'],
            'jeudi' => ['closed' => false, 'open' => '07:00', 'close' => '19:00'],
            'vendredi' => ['closed' => false, 'open' => '07:00', 'close' => '19:00'],
            'samedi' => ['closed' => false, 'open' => '07:00', 'close' => '19:00'],
            'dimanche' => ['closed' => true, 'open' => '', 'close' => ''],
        ]]);

        $days = $this->getJson("/api/v1/pressings/{$this->pressing->id}/pickup-availability")->assertOk()->json('days');

        $this->assertSame(['date' => '2026-11-16', 'label' => 'Aujourd\'hui', 'slots' => Pressing::DEFAULT_DELIVERY_SLOTS], $days[0]);
        $this->assertSame('Demain', $days[1]['label']);
        $this->assertSame('Mercredi 18 novembre', $days[2]['label']);
        $this->assertNotContains('2026-11-22', array_column($days, 'date'), 'Le dimanche est fermé.');
        $this->assertCount(12, $days);

        // À 10 h 30, le créneau 8 h – 12 h du jour n'est plus proposé (moins de 2 h avant sa fin).
        $this->travelTo(Carbon::parse('2026-11-16 10:30'));
        $days = $this->getJson("/api/v1/pressings/{$this->pressing->id}/pickup-availability")->json('days');
        $this->assertSame([['from' => '14:00', 'to' => '18:00']], $days[0]['slots']);
    }

    public function test_only_the_customers_own_pressings_are_offered(): void
    {
        $other = Pressing::factory()->create();

        $this->getJson("/api/v1/pressings/{$other->id}/pickup-availability")->assertNotFound();
        $this->postJson('/api/v1/pickup-requests', $this->payload(['pressing_id' => $other->id]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ajoutez d\'abord ce pressing à vos pressings.');
    }

    public function test_the_customer_sends_a_request_and_the_pressing_admins_are_alerted(): void
    {
        Mail::fake();
        $this->adminWithEmail();

        $this->postJson('/api/v1/pickup-requests', $this->payload())
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('status_label', 'Demande envoyée')
            ->assertJsonPath('schedule_label', 'demain, 08:00 – 12:00')
            ->assertJsonPath('address.commune', 'Cocody')
            ->assertJsonPath('pickup_fee_fcfa', 1000)
            ->assertJsonPath('pressing.name', 'Pressing Élégance')
            ->assertJsonPath('can_cancel', true)
            ->assertJsonPath('can_add_photos', true);

        $request = PickupRequest::sole();
        $this->assertSame('Riviera 3', $request->neighborhood);
        $this->assertSame(12, $request->items_estimate);
        $this->assertTrue(ActivityLog::where('action', ActivityAction::PickupRequested->value)->exists());
        Mail::assertSent(PickupRequestAlert::class, fn (PickupRequestAlert $mail) => $mail->hasTo('gerant@pressing.test') && ! $mail->cancelled);

        // L'adresse modifiée ensuite ne change pas la demande.
        $this->address->update(['landmark' => 'Autre repère']);
        $this->assertNotSame('Autre repère', $request->fresh()->landmark);
    }

    public function test_a_request_is_refused_outside_what_the_pressing_offers(): void
    {
        $abobo = CustomerAddress::factory()->for($this->customer)->create(['commune' => 'Abobo']);

        $this->postJson('/api/v1/pickup-requests', $this->payload(['address_id' => $abobo->id]))
            ->assertUnprocessable()->assertJsonPath('message', 'Pressing Élégance ne passe pas à Abobo.');

        $this->postJson('/api/v1/pickup-requests', $this->payload(['slot_from' => '06:00', 'slot_to' => '07:00']))
            ->assertUnprocessable()->assertJsonPath('message', 'Ce créneau n\'est plus disponible. Choisissez-en un autre.');

        $this->postJson('/api/v1/pickup-requests', $this->payload(['pickup_date' => '2026-12-31']))
            ->assertUnprocessable();

        $someoneElses = CustomerAddress::factory()->create(['commune' => 'Cocody']);
        $this->postJson('/api/v1/pickup-requests', $this->payload(['address_id' => $someoneElses->id]))->assertNotFound();

        $this->pressing->update(['pickup_enabled' => false]);
        $this->postJson('/api/v1/pickup-requests', $this->payload())
            ->assertUnprocessable()->assertJsonPath('message', 'Pressing Élégance ne propose pas le ramassage.');

        $this->pressing->update(['pickup_enabled' => true]);
        config(['presslink.delivery.launched' => false]);
        $this->postJson('/api/v1/pickup-requests', $this->payload())->assertUnprocessable();

        $this->assertSame(0, PickupRequest::count());
    }

    public function test_one_open_request_per_pressing_and_three_in_all(): void
    {
        $this->postJson('/api/v1/pickup-requests', $this->payload())->assertCreated();
        $this->postJson('/api/v1/pickup-requests', $this->payload(['pickup_date' => '2026-11-18']))
            ->assertUnprocessable()->assertJsonPath('message', 'Vous avez déjà une demande en cours chez Pressing Élégance.');

        PickupRequest::factory()->for($this->customer)->count(2)->create();
        $second = Pressing::factory()->create(['opening_hours' => null]);
        Subscription::factory()->for($second)->active(SubscriptionPlan::Premium)->create();
        $second->update(['pickup_enabled' => true]);
        $second->serviceAreas()->create(['commune' => 'Cocody']);
        $second->customers()->attach($this->customer, ['joined_at' => now()]);

        $this->postJson('/api/v1/pickup-requests', $this->payload(['pressing_id' => $second->id]))
            ->assertUnprocessable()->assertJsonPath('message', 'Vous avez déjà 3 demandes de ramassage en cours.');
    }

    public function test_photos_are_added_while_the_request_waits_for_an_answer(): void
    {
        Storage::fake('local');
        $id = $this->postJson('/api/v1/pickup-requests', $this->payload())->json('id');

        $this->post("/api/v1/pickup-requests/{$id}/photos", ['photo' => UploadedFile::fake()->image('linge.jpg', 1200, 900)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(1, 'photos');

        $photo = PickupRequest::find($id)->photos()->sole();
        Storage::disk('local')->assertExists($photo->path);
        $this->get($photo->url(thumb: true))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get(route('pickup-photos.show', $photo))->assertForbidden();

        PickupRequest::find($id)->update(['status' => PickupRequestStatus::Accepted]);
        $this->post("/api/v1/pickup-requests/{$id}/photos", ['photo' => UploadedFile::fake()->image('linge.jpg')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_the_customer_follows_and_cancels_only_their_own_requests(): void
    {
        Mail::fake();
        $this->adminWithEmail();
        $mine = PickupRequest::factory()->for($this->pressing)->for($this->customer)->accepted()->create();
        $someoneElses = PickupRequest::factory()->for($this->pressing)->create();

        $this->getJson('/api/v1/pickup-requests')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $mine->id);
        $this->getJson("/api/v1/pickup-requests/{$someoneElses->id}")->assertNotFound();
        $this->postJson("/api/v1/pickup-requests/{$someoneElses->id}/cancel")->assertNotFound();

        // Le ramassage était prévu : le pressing est prévenu de l'annulation.
        $this->postJson("/api/v1/pickup-requests/{$mine->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('can_cancel', false);
        Mail::assertSent(PickupRequestAlert::class, fn (PickupRequestAlert $mail) => $mail->cancelled);
        $this->assertTrue(ActivityLog::where('action', ActivityAction::PickupCancelled->value)->exists());

        $this->postJson("/api/v1/pickup-requests/{$mine->id}/cancel")->assertUnprocessable();
    }

    public function test_unanswered_requests_expire_at_the_end_of_their_slot(): void
    {
        Notification::fake();
        $overdue = PickupRequest::factory()->for($this->pressing)->for($this->customer)->create(['pickup_date' => '2026-11-16', 'slot_from' => '06:00', 'slot_to' => '08:00']);
        $later = PickupRequest::factory()->for($this->pressing)->create(['pickup_date' => '2026-11-16', 'slot_from' => '08:00', 'slot_to' => '12:00']);
        $accepted = PickupRequest::factory()->for($this->pressing)->accepted()->create(['pickup_date' => '2026-11-15']);

        $this->artisan('presslink:expire-pickups')->assertSuccessful();

        $this->assertSame(PickupRequestStatus::Expired, $overdue->fresh()->status);
        $this->assertNotNull($overdue->fresh()->closed_at);
        $this->assertSame(PickupRequestStatus::Pending, $later->fresh()->status);
        $this->assertSame(PickupRequestStatus::Accepted, $accepted->fresh()->status);
        Notification::assertSentTo($this->customer, PickupExpiredNotification::class);
    }

    public function test_photos_of_finished_requests_are_purged_after_thirty_days(): void
    {
        Storage::fake('local');
        $old = PickupRequest::factory()->for($this->pressing)->create(['status' => PickupRequestStatus::Refused, 'closed_at' => now()->subDays(31)]);
        $recent = PickupRequest::factory()->for($this->pressing)->create(['status' => PickupRequestStatus::Cancelled, 'closed_at' => now()->subDays(5)]);
        foreach ([$old, $recent] as $request) {
            Storage::disk('local')->put("pickup-photos/{$request->id}.webp", 'x');
            $request->photos()->create(['path' => "pickup-photos/{$request->id}.webp", 'thumb_path' => "pickup-photos/{$request->id}-mini.webp"]);
        }

        $this->artisan('presslink:expire-pickups')->assertSuccessful();

        $this->assertSame(0, $old->photos()->count());
        Storage::disk('local')->assertMissing("pickup-photos/{$old->id}.webp");
        $this->assertSame(1, $recent->photos()->count());
    }
}
