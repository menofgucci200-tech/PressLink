<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Admin\Pressings\Create as AdminPressingsCreate;
use App\Livewire\Auth\Login;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Création d'un groupe de pressings mutualisé — le Super Admin crée
 * plusieurs pressings en une fois pour un même propriétaire, qui devient
 * administrateur de chacun et obtient donc le dashboard mutualisé
 * (cf. MultiPressingDashboardTest).
 */
class SuperAdminMultiPressingCreationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_super_admin_can_create_a_group_of_pressings_for_one_owner(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsCreate::class)
            ->set('type', 'multi')
            ->set('ownerName', 'Fatou Diabate')
            ->set('ownerEmail', 'fatou@groupe-nouveau.test')
            ->set('ownerPhone', '+2250701020305')
            ->set('pressingRows.0.name', 'Pressing Groupe Nord')
            ->set('pressingRows.0.phone', '+2250701020306')
            ->set('pressingRows.1.name', 'Pressing Groupe Sud')
            ->set('pressingRows.1.phone', '+2250701020307')
            ->call('createGroup')
            ->assertSet('generatedPassword', fn ($password) => is_string($password) && strlen($password) > 0);

        $owner = User::where('email', 'fatou@groupe-nouveau.test')->firstOrFail();
        $pressingNord = Pressing::where('name', 'Pressing Groupe Nord')->firstOrFail();
        $pressingSud = Pressing::where('name', 'Pressing Groupe Sud')->firstOrFail();

        $this->assertSame(PressingRole::Admin, $pressingNord->staff()->where('users.id', $owner->id)->first()->pivot->role);
        $this->assertSame(PressingRole::Admin, $pressingSud->staff()->where('users.id', $owner->id)->first()->pivot->role);
        $this->assertNotEmpty($pressingNord->code);
        $this->assertNotEmpty($pressingSud->code);
        $this->assertNotNull($pressingNord->subscription);
        $this->assertNotNull($pressingSud->subscription);
        $this->assertTrue($owner->hasMultiplePressings());
    }

    public function test_group_creation_requires_at_least_two_pressings(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsCreate::class)
            ->set('type', 'multi')
            ->set('ownerName', 'Fatou Diabate')
            ->set('ownerEmail', 'fatou@groupe-nouveau.test')
            ->set('ownerPhone', '+2250701020305')
            ->call('removePressingRow', 1)
            ->set('pressingRows.0.name', 'Pressing Solo')
            ->set('pressingRows.0.phone', '+2250701020306')
            ->call('createGroup')
            ->assertHasErrors(['pressingRows']);

        $this->assertDatabaseMissing('pressings', ['name' => 'Pressing Solo']);
    }

    public function test_group_creation_rejects_duplicate_codes_within_the_group(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsCreate::class)
            ->set('type', 'multi')
            ->set('ownerName', 'Fatou Diabate')
            ->set('ownerEmail', 'fatou@groupe-nouveau.test')
            ->set('ownerPhone', '+2250701020305')
            ->set('pressingRows.0.name', 'Pressing Groupe Nord')
            ->set('pressingRows.0.code', 'DUP-01')
            ->set('pressingRows.0.phone', '+2250701020306')
            ->set('pressingRows.1.name', 'Pressing Groupe Sud')
            ->set('pressingRows.1.code', 'dup-01')
            ->set('pressingRows.1.phone', '+2250701020307')
            ->call('createGroup')
            ->assertHasErrors(['pressingRows']);

        $this->assertDatabaseMissing('pressings', ['name' => 'Pressing Groupe Nord']);
    }

    public function test_owner_created_via_group_creation_can_log_in_and_sees_the_overview(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        $component = Livewire::test(AdminPressingsCreate::class)
            ->set('type', 'multi')
            ->set('ownerName', 'Fatou Diabate')
            ->set('ownerEmail', 'fatou@groupe-nouveau.test')
            ->set('ownerPhone', '+2250701020305')
            ->set('pressingRows.0.name', 'Pressing Groupe Nord')
            ->set('pressingRows.0.phone', '+2250701020306')
            ->set('pressingRows.1.name', 'Pressing Groupe Sud')
            ->set('pressingRows.1.phone', '+2250701020307')
            ->call('createGroup');

        $password = $component->get('generatedPassword');

        $this->post('/logout');

        Livewire::test(Login::class)
            ->set('login', 'fatou@groupe-nouveau.test')
            ->set('password', $password)
            ->call('authenticate');

        $this->assertAuthenticatedAs(User::where('email', 'fatou@groupe-nouveau.test')->firstOrFail());
    }

    public function test_standard_pressing_creation_still_works_and_creates_no_admin(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsCreate::class)
            ->assertSet('type', 'standard')
            ->set('name', 'Pressing Simple')
            ->set('phone', '+2250701020304')
            ->call('create')
            ->assertSet('createdPressing', fn ($pressing) => $pressing !== null);

        $pressing = Pressing::where('name', 'Pressing Simple')->firstOrFail();
        $this->assertSame(0, $pressing->staff()->count());
    }
}
