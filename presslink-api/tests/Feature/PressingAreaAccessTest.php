<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PressingAreaAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_is_redirected_to_the_console_instead_of_an_error(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get('/commandes')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_user_without_pressing_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get('/clients')->assertForbidden();
    }
}
