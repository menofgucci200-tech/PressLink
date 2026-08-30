<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Account\Settings as AccountSettings;
use App\Livewire\Auth\Login;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Mon compte" — tout membre du staff (admin ou employé) gère lui-même
 * son propre login/mot de passe, sans passer par le Super Admin.
 */
class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(PressingRole $role = PressingRole::Employee): User
    {
        $pressing = Pressing::factory()->create();
        $user = User::factory()->create(['login' => 'staff.member', 'password' => Hash::make('ancien-mdp-123')]);
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_a_staff_member_can_change_their_own_login(): void
    {
        $user = $this->makeStaff();

        $this->actingAs($user);

        Livewire::test(AccountSettings::class)
            ->set('login', 'nouveau.login')
            ->call('updateLogin')
            ->assertHasNoErrors();

        $this->assertSame('nouveau.login', $user->fresh()->login);
    }

    public function test_login_must_stay_unique(): void
    {
        $user = $this->makeStaff();
        User::factory()->create(['login' => 'deja-pris']);

        $this->actingAs($user);

        Livewire::test(AccountSettings::class)
            ->set('login', 'deja-pris')
            ->call('updateLogin')
            ->assertHasErrors(['login']);
    }

    public function test_a_staff_member_can_change_their_own_password(): void
    {
        $user = $this->makeStaff();

        $this->actingAs($user);

        Livewire::test(AccountSettings::class)
            ->set('currentPassword', 'ancien-mdp-123')
            ->set('newPassword', 'nouveau-mdp-456')
            ->set('newPasswordConfirmation', 'nouveau-mdp-456')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->post('/logout');

        Livewire::test(Login::class)
            ->set('login', 'staff.member')
            ->set('password', 'nouveau-mdp-456')
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_current_password_must_be_correct(): void
    {
        $user = $this->makeStaff();

        $this->actingAs($user);

        Livewire::test(AccountSettings::class)
            ->set('currentPassword', 'mauvais-mot-de-passe')
            ->set('newPassword', 'nouveau-mdp-456')
            ->set('newPasswordConfirmation', 'nouveau-mdp-456')
            ->call('updatePassword')
            ->assertHasErrors(['currentPassword']);
    }

    public function test_the_password_confirmation_must_match(): void
    {
        $user = $this->makeStaff();

        $this->actingAs($user);

        Livewire::test(AccountSettings::class)
            ->set('currentPassword', 'ancien-mdp-123')
            ->set('newPassword', 'nouveau-mdp-456')
            ->set('newPasswordConfirmation', 'ne-correspond-pas')
            ->call('updatePassword')
            ->assertHasErrors(['newPasswordConfirmation']);
    }
}
