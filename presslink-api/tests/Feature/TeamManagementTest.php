<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Team\Index as TeamIndex;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_employee_cannot_access_the_team_page(): void
    {
        $pressing = Pressing::factory()->create();
        $employee = $this->makeStaff($pressing, PressingRole::Employee);

        $this->actingAs($employee);

        Livewire::test(TeamIndex::class)->assertStatus(403);
    }

    public function test_admin_can_add_a_new_employee(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(TeamIndex::class)
            ->set('name', 'Marc Koffi')
            ->set('login', 'marc.koffi')
            ->set('phone', '+2250700000099')
            ->set('password', 'mot-de-passe-solide')
            ->set('role', PressingRole::Employee->value)
            ->call('inviteEmployee')
            ->assertSet('createdMember', fn ($member) => $member !== null);

        $employee = User::where('login', 'marc.koffi')->firstOrFail();
        $this->assertTrue($pressing->staff()->where('users.id', $employee->id)->exists());
        $this->assertSame(
            PressingRole::Employee,
            $pressing->staff()->where('users.id', $employee->id)->first()->pivot->role,
        );
    }

    public function test_admin_can_add_another_admin_for_their_own_pressing(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(TeamIndex::class)
            ->set('name', 'Aya N\'Guessan')
            ->set('login', 'aya.nguessan')
            ->set('password', 'mot-de-passe-solide')
            ->set('role', PressingRole::Admin->value)
            ->call('inviteEmployee')
            ->assertSet('createdMember', fn ($member) => $member !== null);

        $newAdmin = User::where('login', 'aya.nguessan')->firstOrFail();
        $this->assertSame(
            PressingRole::Admin,
            $pressing->staff()->where('users.id', $newAdmin->id)->first()->pivot->role,
        );
    }

    public function test_admin_cannot_add_a_member_with_a_duplicate_login(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        User::factory()->create(['login' => 'taken-login']);

        $this->actingAs($admin);

        Livewire::test(TeamIndex::class)
            ->set('name', 'Marc Koffi')
            ->set('login', 'taken-login')
            ->set('phone', '+2250700000099')
            ->set('password', 'mot-de-passe-solide')
            ->set('role', PressingRole::Employee->value)
            ->call('inviteEmployee')
            ->assertHasErrors(['login']);
    }

    public function test_admin_can_deactivate_an_employee(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $employee = $this->makeStaff($pressing, PressingRole::Employee);

        $this->actingAs($admin);

        Livewire::test(TeamIndex::class)->call('toggleActive', $employee->id);

        $this->assertFalse(
            $pressing->staff()->where('users.id', $employee->id)->first()->pivot->is_active,
        );
    }

    public function test_admin_cannot_remove_themselves_from_the_team(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(TeamIndex::class)
            ->call('toggleActive', $admin->id)
            ->assertSet('errorMessage', fn ($message) => $message !== null);

        $this->assertTrue(
            $pressing->staff()->where('users.id', $admin->id)->first()->pivot->is_active,
        );
    }

    public function test_the_last_active_admin_cannot_be_removed(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $otherAdmin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(TeamIndex::class)->call('toggleActive', $otherAdmin->id);
        $this->assertFalse($pressing->staff()->where('users.id', $otherAdmin->id)->first()->pivot->is_active);

        Livewire::test(TeamIndex::class)
            ->call('toggleActive', $admin->id)
            ->assertSet('errorMessage', fn ($message) => $message !== null);

        $this->assertTrue(
            $pressing->staff()->where('users.id', $admin->id)->first()->pivot->is_active,
        );
    }
}
