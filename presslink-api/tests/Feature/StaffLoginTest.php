<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_guests_to_login(): void
    {
        $this->get('/espace/tableau-de-bord')->assertRedirect(route('login'));
    }

    public function test_old_addresses_lead_to_the_same_page_under_espace(): void
    {
        $this->get('/login')->assertRedirect('/espace/connexion');
        $this->get('/tableau-de-bord')->assertRedirect('/espace/tableau-de-bord')->assertStatus(308);
        $this->get('/commandes/12?status=prete')->assertRedirect('/espace/commandes/12?status=prete');
        $this->post('/comptoir-hors-ligne/envoyer')->assertStatus(308)->assertRedirect('/espace/comptoir-hors-ligne/envoyer');
        $this->get('/pour-les-pressings')->assertOk();
    }

    public function test_the_installed_pro_software_does_not_cover_the_customer_app(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest-espace.json')), true);

        // L'identité ne change pas (logiciels déjà installés sur les PC) ; le périmètre exclut /app/.
        $this->assertSame('/tableau-de-bord', $manifest['id']);
        $this->assertSame('/espace/', $manifest['scope']);
        $this->assertStringStartsWith('/espace/', $manifest['start_url']);
        $this->assertStringStartsWith('/espace/', parse_url(route('dashboard'), PHP_URL_PATH));
        $this->assertStringStartsWith('/espace/', parse_url(route('login'), PHP_URL_PATH));
    }

    public function test_staff_can_log_in_with_email(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        Livewire::test(Login::class)
            ->set('login', $user->email)
            ->set('password', 'secret123')
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_stay_logged_in_by_default(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        Livewire::test(Login::class)
            ->assertSet('remember', true)
            ->set('login', $user->email)
            ->set('password', 'secret123')
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Cookie::hasQueued(Auth::guard()->getRecallerName()));
    }

    public function test_staff_can_untick_stay_logged_in_on_a_shared_computer(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        Livewire::test(Login::class)
            ->set('login', $user->email)
            ->set('password', 'secret123')
            ->set('remember', false)
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(Cookie::hasQueued(Auth::guard()->getRecallerName()));
    }

    public function test_staff_can_log_in_with_phone(): void
    {
        $user = User::factory()->create(['phone' => '+2250700000099', 'password' => Hash::make('secret123')]);

        Livewire::test(Login::class)
            ->set('login', '+2250700000099')
            ->set('password', 'secret123')
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        Livewire::test(Login::class)
            ->set('login', $user->email)
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors('login');

        $this->assertGuest();
    }
}
