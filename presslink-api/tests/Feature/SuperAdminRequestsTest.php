<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Livewire\Admin\Pressings\Show as AdminPressingShow;
use App\Livewire\Admin\Requests\Index as AdminRequests;
use App\Livewire\Subscription\Show as SubscriptionShow;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Super Admin : page « Demandes et paiements », refus avec motif,
 * historique des paiements, prolongation en un clic et équipe d'un pressing.
 */
class SuperAdminRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Pressing $pressing;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_super_admin' => true, 'name' => 'Équipe PL']);
        $this->pressing = Pressing::factory()->create(['name' => 'Pressing Étoile']);
        Subscription::factory()->for($this->pressing)->active(SubscriptionPlan::Starter)->create();
        $this->owner = User::factory()->create();
        $this->pressing->staff()->attach($this->owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);
    }

    private function request(array $attributes = []): SubscriptionRequest
    {
        return SubscriptionRequest::create([
            'pressing_id' => $this->pressing->id,
            'requested_by' => $this->owner->id,
            'kind' => SubscriptionRequest::KIND_ORDERS,
            'extra_orders' => 100,
            'amount_fcfa' => 1500,
            'status' => SubscriptionRequest::PENDING,
            ...$attributes,
        ]);
    }

    public function test_only_the_super_admin_opens_the_requests_page(): void
    {
        $this->actingAs($this->owner)->get(route('admin.requests.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.requests.index'))->assertOk()->assertSee('Demandes et paiements');
    }

    public function test_pending_requests_are_handled_from_the_requests_page_and_become_payments(): void
    {
        $request = $this->request();

        Livewire::actingAs($this->admin)->test(AdminRequests::class)
            ->assertSee('Pressing Étoile')
            ->assertSee('100 commandes en plus ce mois-ci')
            ->call('applyRequest', $request->id);

        $request->refresh();
        $this->assertSame(SubscriptionRequest::DONE, $request->status);
        $this->assertSame($this->admin->id, $request->handled_by);

        Livewire::actingAs($this->admin)->test(AdminRequests::class)
            ->set('tab', 'paiements')
            ->assertSee('1 500 F')
            ->assertSee('Équipe PL')
            ->assertViewHas('totals', fn (array $totals) => $totals['Ce mois-ci'] === 1500);

        Livewire::actingAs($this->owner)->test(SubscriptionShow::class)
            ->assertSee('Mes paiements')
            ->assertSee('1 500 F');
    }

    public function test_a_declined_request_keeps_its_reason_and_the_pressing_sees_it(): void
    {
        $request = $this->request();

        Livewire::actingAs($this->admin)->test(AdminRequests::class)
            ->set("declineReasons.{$request->id}", 'Paiement non reçu après 7 jours')
            ->call('declineRequest', $request->id);

        $this->assertSame('Paiement non reçu après 7 jours', $request->fresh()->decline_reason);
        $this->assertSame(SubscriptionRequest::DECLINED, $request->fresh()->status);

        Livewire::actingAs($this->owner)->test(SubscriptionShow::class)
            ->assertSee('Demandes refusées')
            ->assertSee('Paiement non reçu après 7 jours');

        Livewire::actingAs($this->admin)->test(AdminPressingShow::class, ['pressing' => $this->pressing])
            ->assertSee('Motif : Paiement non reçu après 7 jours');
    }

    public function test_the_requests_page_searches_by_pressing(): void
    {
        $this->request();
        $other = Pressing::factory()->create(['name' => 'Lavage Express']);
        SubscriptionRequest::create(['pressing_id' => $other->id, 'kind' => SubscriptionRequest::KIND_ORDERS, 'extra_orders' => 50, 'status' => SubscriptionRequest::PENDING]);

        Livewire::actingAs($this->admin)->test(AdminRequests::class)
            ->set('search', 'Lavage')
            ->assertSee('Lavage Express')
            ->assertDontSee('Pressing Étoile');
    }

    public function test_one_click_extensions_push_the_dates_from_the_end_or_from_today(): void
    {
        $subscription = $this->pressing->subscription;
        $subscription->update(['status' => SubscriptionStatus::Active, 'current_period_ends_at' => now()->addDays(10)]);

        Livewire::actingAs($this->admin)->test(AdminPressingShow::class, ['pressing' => $this->pressing])
            ->call('extend', 'month');
        $this->assertTrue($subscription->fresh()->current_period_ends_at->isSameDay(now()->addDays(10)->addMonthNoOverflow()));

        $subscription->update(['status' => SubscriptionStatus::Trialing, 'trial_ends_at' => now()->subDays(2)]);
        Livewire::actingAs($this->admin)->test(AdminPressingShow::class, ['pressing' => $this->pressing->fresh()])
            ->call('extend', 'trial');
        $this->assertTrue($subscription->fresh()->trial_ends_at->isSameDay(now()->addDays(7)));
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->fresh()->status);
    }

    public function test_the_super_admin_edits_the_team_from_the_pressing_page(): void
    {
        $employeeRole = $this->pressing->defaultRole(Role::EMPLOYEE);
        $ownerRole = $this->pressing->defaultRole(Role::OWNER);

        Livewire::actingAs($this->admin)->test(AdminPressingShow::class, ['pressing' => $this->pressing])
            ->set('newMember', ['name' => 'Awa Koné', 'login' => 'awa.kone', 'phone' => '', 'password' => 'motdepasse', 'roleId' => (string) $employeeRole->id])
            ->call('addMember')
            ->assertHasNoErrors();

        $awa = User::where('login', 'awa.kone')->firstOrFail();
        $this->assertTrue($this->pressing->staff()->whereKey($awa->id)->wherePivot('role_id', $employeeRole->id)->exists());

        $page = Livewire::actingAs($this->admin)->test(AdminPressingShow::class, ['pressing' => $this->pressing]);

        // Le dernier propriétaire ne perd ni son rôle ni son accès.
        $page->call('changeMemberRole', $this->owner->id, $employeeRole->id);
        $page->call('toggleMember', $this->owner->id);
        $owner = $this->pressing->staff()->whereKey($this->owner->id)->first();
        $this->assertSame(PressingRole::Admin, $owner->pivot->role);
        $this->assertTrue((bool) $owner->pivot->is_active);

        $page->call('changeMemberRole', $awa->id, $ownerRole->id);
        $this->assertSame(PressingRole::Admin, $this->pressing->staff()->whereKey($awa->id)->first()->pivot->role);

        $page->call('toggleMember', $this->owner->id);
        $this->assertFalse((bool) $this->pressing->staff()->whereKey($this->owner->id)->first()->pivot->is_active);
    }
}
