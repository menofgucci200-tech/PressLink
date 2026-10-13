<?php

namespace Tests\Feature;

use App\Livewire\Auth\AdminLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Portail de la console super admin (/admin/connexion), distinct de
 * l'espace pressing.
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_portal_is_reachable_and_not_indexable(): void
    {
        $this->get('/admin/connexion')
            ->assertOk()
            ->assertSee('Console d\'administration', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_guests_hitting_admin_pages_land_on_the_admin_portal(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/tableau-de-bord')->assertRedirect(route('login'));
    }

    public function test_super_admin_can_log_in_through_the_admin_portal(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'password' => Hash::make('secret123')]);

        Livewire::test(AdminLogin::class)
            ->set('login', $admin->email)
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_pressing_staff_cannot_log_in_through_the_admin_portal(): void
    {
        $staff = User::factory()->create(['password' => Hash::make('secret123')]);

        Livewire::test(AdminLogin::class)
            ->set('login', $staff->email)
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertHasErrors('login');

        $this->assertGuest();
    }

    public function test_super_admin_logout_returns_to_the_admin_portal(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $this->post('/logout')->assertRedirect(route('admin.login'));
    }
}
