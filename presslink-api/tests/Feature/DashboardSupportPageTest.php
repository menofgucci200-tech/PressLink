<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Support\Show as SupportShow;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardSupportPageTest extends TestCase
{
    use RefreshDatabase;

    private function staff(PressingRole $role): array
    {
        $pressing = Pressing::factory()->create(['name' => 'Pressing Étoile']);
        $user = User::factory()->create(['name' => 'Awa Kouassi']);
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return [$pressing, $user];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/support')->assertRedirect('/login');
    }

    public function test_employees_and_admins_can_open_the_support_page(): void
    {
        foreach ([PressingRole::Employee, PressingRole::Admin] as $role) {
            [, $user] = $this->staff($role);

            $this->actingAs($user)
                ->get('/support')
                ->assertOk()
                ->assertSee(config('presslink.support.phone_display'))
                ->assertSee('tel:'.config('presslink.support.phone_e164'), false)
                ->assertSee('Questions fréquentes');
        }
    }

    public function test_whatsapp_message_is_prefilled_with_member_and_pressing_names(): void
    {
        [, $user] = $this->staff(PressingRole::Employee);
        $this->actingAs($user);

        Livewire::test(SupportShow::class)
            ->assertSee('https://wa.me/2250718002293?text=', false)
            ->assertSee(rawurlencode('Awa Kouassi'), false)
            ->assertSee(rawurlencode('Pressing Étoile'), false);
    }

    public function test_support_link_is_in_the_sidebar_for_every_staff_member(): void
    {
        [, $user] = $this->staff(PressingRole::Employee);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('support'), false)
            ->assertSee('aria-label="Ouvrir le menu"', false);
    }
}
