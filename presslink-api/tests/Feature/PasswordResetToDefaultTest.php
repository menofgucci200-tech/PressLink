<?php

namespace Tests\Feature;

use App\Actions\Auth\ResetPasswordToDefault;
use App\Enums\PressingRole;
use App\Livewire\Account\Settings as AccountSettings;
use App\Livewire\Admin\Users\Index as AdminUsersIndex;
use App\Livewire\Clients\Show as ClientsShow;
use App\Livewire\Team\Index as TeamIndex;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Réinitialisation des mots de passe à la valeur par défaut (123456) :
 * liste des utilisateurs du Super Admin et équipe du dashboard pressing,
 * puis changement par l'utilisateur lui-même. Un pressing ne peut plus
 * réinitialiser le mot de passe d'un client.
 */
class PasswordResetToDefaultTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Pressing $pressing, PressingRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'name' => 'Équipe PressLink']);
    }

    public function test_the_default_password_is_123456(): void
    {
        $this->assertSame('123456', ResetPasswordToDefault::DEFAULT_PASSWORD);
        $this->assertSame('123456', Customer::DEFAULT_WALK_IN_PASSWORD);
    }

    public function test_users_list_shows_every_kind_of_account(): void
    {
        $pressing = Pressing::factory()->create();
        $this->staff($pressing, PressingRole::Employee, ['name' => 'Moussa Employé']);
        Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Cliente']);
        $this->actingAs($this->superAdmin());

        $this->get(route('admin.users.index'))->assertOk()->assertSee('Liste des utilisateurs');

        Livewire::test(AdminUsersIndex::class)
            ->assertSee('Moussa Employé')
            ->set('type', 'customers')
            ->assertSee('Awa Cliente')
            ->assertDontSee('Moussa Employé')
            ->set('type', 'admins')
            ->assertSee('Équipe PressLink');
    }

    public function test_super_admin_resets_a_staff_password_to_the_default(): void
    {
        $pressing = Pressing::factory()->create();
        $member = $this->staff($pressing, PressingRole::Admin, ['password' => 'ancien-secret']);
        $this->actingAs($this->superAdmin());

        Livewire::test(AdminUsersIndex::class)
            ->call('resetUserPassword', $member->id)
            ->assertDispatched('toast');

        $member->refresh();
        $this->assertTrue(Hash::check('123456', $member->password));
        $this->assertTrue($member->must_change_password);

        Livewire::test(AdminUsersIndex::class)
            ->set('mustChangeOnly', true)
            ->assertViewHas('accounts', fn ($accounts) => $accounts->pluck('id')->all() === [$member->id]);
    }

    public function test_super_admin_resets_a_customer_password_and_closes_app_sessions(): void
    {
        $customer = Customer::factory()->create(['password' => 'ancien-secret']);
        $customer->createToken('customer-app');
        $this->actingAs($this->superAdmin());

        Livewire::test(AdminUsersIndex::class)->call('resetCustomerPassword', $customer->id);

        $customer->refresh();
        $this->assertTrue(Hash::check('123456', $customer->password));
        $this->assertTrue($customer->must_change_password);
        $this->assertSame(0, $customer->tokens()->count());
    }

    public function test_super_admin_cannot_reset_their_own_password_from_the_list(): void
    {
        $me = $this->superAdmin();
        $original = $me->password;
        $this->actingAs($me);

        Livewire::test(AdminUsersIndex::class)
            ->call('resetUserPassword', $me->id)
            ->assertDispatched('toast', type: 'warning');

        $this->assertSame($original, $me->refresh()->password);
    }

    public function test_pressing_admin_resets_a_team_member_password(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->staff($pressing, PressingRole::Admin);
        $employee = $this->staff($pressing, PressingRole::Employee, ['password' => 'ancien-secret']);
        $this->actingAs($admin);

        Livewire::test(TeamIndex::class)
            ->call('resetMemberPassword', $employee->id)
            ->assertDispatched('toast');

        $this->assertTrue(Hash::check('123456', $employee->refresh()->password));
    }

    public function test_a_pressing_can_no_longer_reset_a_client_password(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->staff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create(['password' => 'ancien-secret']);
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        $this->actingAs($admin);
        Livewire::test(ClientsShow::class, ['customer' => $customer])
            ->assertDontSee('Réinitialiser le mot de passe')
            ->assertSee('Utilise l\'application', false);

        $this->assertFalse(method_exists(ClientsShow::class, 'resetPassword'));
        $this->assertTrue(Hash::check('ancien-secret', $customer->refresh()->password));
    }

    public function test_no_banner_is_shown_and_changing_the_password_clears_the_flag(): void
    {
        $pressing = Pressing::factory()->create();
        $member = $this->staff($pressing, PressingRole::Admin);
        (new ResetPasswordToDefault)->handle($member);
        $this->actingAs($member->refresh());

        $this->get(route('dashboard'))->assertOk()->assertDontSee('Votre mot de passe a été réinitialisé');

        Livewire::test(AccountSettings::class)
            ->set('currentPassword', '123456')
            ->set('newPassword', 'nouveau-secret')
            ->set('newPasswordConfirmation', 'nouveau-secret')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertFalse($member->refresh()->must_change_password);
    }

    public function test_the_reset_confirmation_tells_to_change_the_password_afterwards(): void
    {
        $pressing = Pressing::factory()->create();
        $this->staff($pressing, PressingRole::Employee);
        $this->actingAs($this->superAdmin());

        Livewire::test(AdminUsersIndex::class)
            ->assertSee('Après la réinitialisation, il doit changer son mot de passe pour sécuriser son compte.');
    }

    public function test_super_admin_has_their_own_account_page_in_the_console(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get(route('account.settings'))->assertOk()->assertSee('Super Admin')->assertSee('Mon profil');
    }

    public function test_the_app_is_told_to_ask_for_a_new_password_until_the_customer_changes_it(): void
    {
        $customer = Customer::factory()->create(['phone' => '+2250701020304', 'password' => 'secret123']);
        (new ResetPasswordToDefault)->handle($customer);

        $token = $this->postJson('/api/v1/auth/customer/login', ['phone' => '+2250701020304', 'password' => '123456'])
            ->assertOk()
            ->assertJsonPath('customer.must_change_password', true)
            ->json('token');

        $headers = ['Authorization' => 'Bearer '.$token];
        $this->putJson('/api/v1/customer/password', [
            'current_password' => '123456',
            'password' => 'nouveau-secret',
            'password_confirmation' => 'nouveau-secret',
        ], $headers)->assertOk();

        $this->getJson('/api/v1/auth/customer/me', $headers)->assertJsonPath('must_change_password', false);
    }
}
