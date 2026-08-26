<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
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
