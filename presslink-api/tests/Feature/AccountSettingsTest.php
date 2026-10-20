<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Account\Settings as AccountSettings;
use App\Livewire\Auth\Login;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Mon profil" — tout membre du staff (admin ou employé) gère lui-même
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

    public function test_a_staff_member_can_personalise_their_profile(): void
    {
        $user = $this->makeStaff();

        $this->actingAs($user);

        Livewire::test(AccountSettings::class)
            ->set('name', 'Aya Koné')
            ->set('email', 'Aya.Kone@Exemple.ci')
            ->set('phone', '07 08 12 44 00')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertRedirect(route('account.settings'));

        $user->refresh();
        $this->assertSame('Aya Koné', $user->name);
        $this->assertSame('aya.kone@exemple.ci', $user->email);
        $this->assertSame('+2250708124400', $user->phone);
        $this->assertSame('AK', $user->initials);
    }

    public function test_profile_email_and_phone_must_stay_unique(): void
    {
        $user = $this->makeStaff();
        User::factory()->create(['email' => 'pris@exemple.ci', 'phone' => '+2250700000099']);

        $this->actingAs($user);

        Livewire::test(AccountSettings::class)
            ->set('email', 'pris@exemple.ci')
            ->set('phone', '+2250700000099')
            ->call('updateProfile')
            ->assertHasErrors(['email', 'phone']);
    }

    public function test_an_account_without_login_keeps_a_way_to_sign_in(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'login' => null, 'phone' => null]);

        $this->actingAs($superAdmin);

        Livewire::test(AccountSettings::class)
            ->set('email', '')
            ->call('updateProfile')
            ->assertHasErrors(['email' => 'required']);

        $this->assertNotNull($superAdmin->fresh()->email);
    }

    public function test_a_profile_photo_is_stored_reduced_and_replaces_the_previous_one(): void
    {
        Storage::fake('public');
        $user = $this->makeStaff();

        $this->actingAs($user);

        Livewire::test(AccountSettings::class)
            ->set('avatar', UploadedFile::fake()->image('moi.jpg', 2000, 2000))
            ->assertHasNoErrors();

        $first = $user->fresh()->avatar_path;
        $this->assertStringStartsWith('avatars/', $first);
        $this->assertStringEndsWith('.webp', $first);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($first));
        $this->assertSame([512, 512], [$width, $height]);

        Livewire::test(AccountSettings::class)
            ->set('avatar', UploadedFile::fake()->image('nouvelle.png', 300, 300));

        $second = $user->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->get(route('dashboard'))->assertOk()->assertSee($user->fresh()->avatar_url, false);
    }

    public function test_a_profile_photo_can_be_removed(): void
    {
        Storage::fake('public');
        $user = $this->makeStaff();
        Storage::disk('public')->put('avatars/ancienne.webp', 'image');
        $user->update(['avatar_path' => 'avatars/ancienne.webp']);

        $this->actingAs($user);

        Livewire::test(AccountSettings::class)->call('removeAvatar');

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing('avatars/ancienne.webp');
    }

    public function test_a_non_image_file_is_refused_as_profile_photo(): void
    {
        Storage::fake('public');
        $user = $this->makeStaff();

        $this->actingAs($user);

        Livewire::test(AccountSettings::class)
            ->set('avatar', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))
            ->assertHasErrors(['avatar']);

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_both_dashboards_offer_the_profile_menu(): void
    {
        $this->actingAs($this->makeStaff(PressingRole::Admin))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mon profil')
            ->assertSee('Mot de passe et connexion')
            ->assertSee('Paramètres du pressing')
            ->assertSee('Se déconnecter');

        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Mon profil')
            ->assertSee('Se déconnecter');
    }
}
