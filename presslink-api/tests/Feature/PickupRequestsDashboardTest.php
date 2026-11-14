<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\PickupRequestStatus;
use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Livewire\Pickups\Index;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\PickupRequest;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PickupAcceptedNotification;
use App\Notifications\PickupRefusedNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ramassage et livraison, lot 2 (côté pressing) : menu « Ramassages »,
 * accepter sur le créneau du client ou un autre, refuser avec un motif,
 * reprogrammer un ramassage prévu.
 */
class PickupRequestsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private User $owner;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['presslink.delivery.launched' => true]);
        $this->travelTo(Carbon::parse('2026-11-16 09:00'));

        $this->pressing = Pressing::factory()->create(['opening_hours' => null]);
        Subscription::factory()->for($this->pressing)->active(SubscriptionPlan::Business)->create();
        $this->pressing->update(['pickup_enabled' => true]);
        $this->owner = User::factory()->create();
        $this->pressing->staff()->attach($this->owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $this->customer = Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné']);
    }

    private function pending(array $attributes = []): PickupRequest
    {
        return PickupRequest::factory()->for($this->pressing)->for($this->customer)->create($attributes);
    }

    public function test_the_menu_shows_pickups_with_the_number_to_handle(): void
    {
        $this->pending();
        $this->actingAs($this->owner);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee(route('pickups.index'), false)
            ->assertSeeInOrder(['data-testid="pickups-badge"', '1'], false);

        config(['presslink.delivery.launched' => false]);
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('pickups.index'), false);
    }

    public function test_the_page_needs_the_feature(): void
    {
        $starter = Pressing::factory()->create();
        Subscription::factory()->for($starter)->active(SubscriptionPlan::Starter)->create();
        $user = User::factory()->create();
        $starter->staff()->attach($user, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        $this->actingAs($user)->get(route('pickups.index'))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee(route('pickups.index'), false);
    }

    public function test_the_pressing_accepts_a_request_on_the_customers_slot(): void
    {
        Notification::fake();
        $request = $this->pending();
        $this->actingAs($this->owner);

        $this->get(route('pickups.index'))->assertOk()->assertSee('Awa Koné')->assertSee('Demain, 08:00 – 12:00');

        Livewire::test(Index::class)
            ->call('startAccept', $request->id)
            ->assertSet('slot', '08:00-12:00')
            ->call('confirmAccept')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success', message: 'Ramassage accepté : le client est prévenu.');

        $request->refresh();
        $this->assertSame(PickupRequestStatus::Accepted, $request->status);
        $this->assertFalse($request->rescheduled);
        $this->assertSame($this->owner->id, $request->responded_by);
        $this->assertTrue(ActivityLog::where('action', ActivityAction::PickupAccepted->value)->exists());
        Notification::assertSentTo($this->customer, PickupAcceptedNotification::class,
            fn (PickupAcceptedNotification $n) => $n->title() === 'Ramassage prévu' && str_contains($n->body(), 'demain, 08:00 – 12:00'));
    }

    public function test_the_pressing_can_propose_another_slot_and_reschedule_later(): void
    {
        Notification::fake();
        $request = $this->pending();
        $this->actingAs($this->owner);

        Livewire::test(Index::class)
            ->call('startAccept', $request->id)
            ->set('date', '2026-11-18')
            ->set('slot', '14:00-18:00')
            ->call('confirmAccept')
            ->assertHasNoErrors();

        $request->refresh();
        $this->assertSame('2026-11-18', $request->pickup_date->toDateString());
        $this->assertSame('14:00', $request->slot_from);
        $this->assertTrue($request->rescheduled);
        Notification::assertSentTo($this->customer, PickupAcceptedNotification::class, fn ($n) => $n->title() === 'Ramassage reprogrammé');

        // Un ramassage prévu se reprogramme aussi.
        Livewire::test(Index::class, ['tab' => 'accepted'])
            ->call('startAccept', $request->id)
            ->set('date', '2026-11-19')
            ->call('confirmAccept')
            ->assertDispatched('toast', type: 'success', message: 'Ramassage reprogrammé : le client est prévenu.');
        $this->assertSame('2026-11-19', $request->fresh()->pickup_date->toDateString());

        // Un jour hors de la liste est refusé.
        Livewire::test(Index::class, ['tab' => 'accepted'])
            ->call('startAccept', $request->id)
            ->set('date', '2027-03-01')
            ->call('confirmAccept')
            ->assertHasErrors('date');
    }

    public function test_the_pressing_refuses_with_a_reason_sent_to_the_customer(): void
    {
        Notification::fake();
        $request = $this->pending();
        $this->actingAs($this->owner);

        Livewire::test(Index::class)
            ->call('startRefuse', $request->id)
            ->set('reason', 'other')
            ->call('confirmRefuse')
            ->assertHasErrors('otherReason')
            ->set('otherReason', 'Livreur malade aujourd\'hui')
            ->call('confirmRefuse')
            ->assertHasNoErrors();

        $request->refresh();
        $this->assertSame(PickupRequestStatus::Refused, $request->status);
        $this->assertSame('Livreur malade aujourd\'hui', $request->refusal_reason);
        Notification::assertSentTo($this->customer, PickupRefusedNotification::class,
            fn (PickupRefusedNotification $n) => str_contains($n->body(), 'Livreur malade aujourd\'hui'));

        // Une demande terminée ne se traite plus.
        Livewire::test(Index::class, ['tab' => 'closed'])
            ->assertSee('Livreur malade')
            ->call('startAccept', $request->id)
            ->call('confirmAccept')
            ->assertDispatched('toast', type: 'error');
        $this->assertSame(PickupRequestStatus::Refused, $request->fresh()->status);
    }

    public function test_a_member_without_the_orders_permission_only_reads_the_list(): void
    {
        $request = $this->pending();
        $role = Role::create(['pressing_id' => $this->pressing->id, 'key' => 'laveur', 'name' => 'Laveur', 'permissions' => []]);
        $washer = User::factory()->create();
        $this->pressing->staff()->attach($washer, ['role' => PressingRole::Employee->value, 'role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($washer);

        $this->get(route('pickups.index'))->assertOk()->assertSee('Awa Koné')->assertDontSee('wire:click="startAccept', false);
        Livewire::test(Index::class)->call('startAccept', $request->id)->call('confirmAccept')->assertForbidden();
        $this->assertSame(PickupRequestStatus::Pending, $request->fresh()->status);
    }

    public function test_requests_of_another_pressing_are_out_of_reach(): void
    {
        $other = PickupRequest::factory()->create();
        $this->actingAs($this->owner);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(Index::class)->call('startAccept', $other->id);
    }
}
