<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarkModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_site_applies_the_saved_theme_and_offers_the_toggle(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('window.PressLinkTheme', false)
            ->assertSee('PressLinkTheme.toggle()', false)
            ->assertSee('Activer le mode nuit');
    }

    public function test_pressing_dashboard_offers_the_toggle(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = User::factory()->create();
        $pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('window.PressLinkTheme', false)
            ->assertSee('PressLinkTheme.toggle()', false);
    }

    public function test_super_admin_console_offers_the_toggle(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('window.PressLinkTheme', false)
            ->assertSee('PressLinkTheme.toggle()', false);
    }

    public function test_error_pages_follow_the_saved_theme(): void
    {
        $this->get('/page-qui-nexiste-pas')
            ->assertNotFound()
            ->assertSee("localStorage.getItem('presslink-theme')", false)
            ->assertSee(':root[data-theme=dark]', false);
    }
}
