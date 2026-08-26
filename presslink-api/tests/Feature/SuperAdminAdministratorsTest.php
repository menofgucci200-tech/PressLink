<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Admin\Administrators\Index as AdminAdministratorsIndex;
use App\Livewire\Auth\Login;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un pressing nouvellement créé n'a pas d'administrateur (cf. Pressings\Create
 * qui ne crée plus que le pressing + son abonnement d'essai) : c'est ce
 * module qui permet au Super Admin de lui en assigner un.
 */
class SuperAdminAdministratorsTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_regular_staff_cannot_access_the_administrators_page(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(AdminAdministratorsIndex::class)->assertStatus(403);
    }

    public function test_super_admin_can_create_an_administrator_for_a_pressing(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $pressing = Pressing::factory()->create(['name' => 'Pressing Sans Admin']);

        $this->actingAs($superAdmin);

        Livewire::test(AdminAdministratorsIndex::class)
            ->set('pressingId', (string) $pressing->id)
            ->set('name', 'Fatou Diabate')
            ->set('email', 'fatou@pressing-nouveau.test')
            ->set('phone', '+2250701020305')
            ->call('createAdministrator')
            ->assertSet('generatedPassword', fn ($password) => is_string($password) && strlen($password) > 0);

        $admin = User::where('email', 'fatou@pressing-nouveau.test')->firstOrFail();

        $this->assertSame(
            PressingRole::Admin,
            $pressing->staff()->where('users.id', $admin->id)->first()->pivot->role,
        );
    }

    public function test_the_created_administrator_can_log_in(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $pressing = Pressing::factory()->create();

        $this->actingAs($superAdmin);

        $component = Livewire::test(AdminAdministratorsIndex::class)
            ->set('pressingId', (string) $pressing->id)
            ->set('name', 'Fatou Diabate')
            ->set('email', 'fatou@pressing-nouveau.test')
            ->set('phone', '+2250701020305')
            ->call('createAdministrator');

        $password = $component->get('generatedPassword');

        $this->post('/logout');

        Livewire::test(Login::class)
            ->set('login', 'fatou@pressing-nouveau.test')
            ->set('password', $password)
            ->call('authenticate');

        $this->assertAuthenticatedAs(User::where('email', 'fatou@pressing-nouveau.test')->firstOrFail());
    }

    public function test_administrators_list_shows_the_pressings_they_administer(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $pressing = Pressing::factory()->create(['name' => 'Pressing Élégance']);
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $employeeOnly = $this->makeStaff($pressing, PressingRole::Employee);

        $this->actingAs($superAdmin);

        Livewire::test(AdminAdministratorsIndex::class)
            ->assertSee($admin->name)
            ->assertSee('Pressing Élégance')
            ->assertDontSee($employeeOnly->name);
    }

    public function test_preselecting_a_pressing_via_query_string_opens_the_create_form(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $pressing = Pressing::factory()->create(['name' => 'Pressing Préselectionné']);

        $this->actingAs($superAdmin)
            ->get(route('admin.administrators.index', ['pressing' => $pressing->id]))
            ->assertOk()
            ->assertSee('Pressing Préselectionné');
    }
}
