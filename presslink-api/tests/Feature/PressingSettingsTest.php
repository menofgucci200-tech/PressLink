<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Pressing\Settings as PressingSettings;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PressingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_employee_cannot_access_the_settings_page(): void
    {
        $pressing = Pressing::factory()->create();
        $employee = $this->makeStaff($pressing, PressingRole::Employee);

        $this->actingAs($employee);

        Livewire::test(PressingSettings::class)->assertStatus(403);
    }

    public function test_admin_can_update_the_pressing_information(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(PressingSettings::class)
            ->set('name', 'Pressing Nouveau Nom')
            ->set('phone', '+2250701020304')
            ->set('address', 'Rue des Jardins')
            ->set('city', 'Marcory')
            ->set('openingHours.lundi.closed', true)
            ->call('save')
            ->assertSet('saved', true);

        $pressing->refresh();
        $this->assertSame('Pressing Nouveau Nom', $pressing->name);
        $this->assertSame('+2250701020304', $pressing->phone);
        $this->assertSame('Rue des Jardins', $pressing->address);
        $this->assertSame('Marcory', $pressing->city);
        $this->assertTrue($pressing->opening_hours['lundi']['closed']);
    }

    public function test_admin_can_upload_a_logo(): void
    {
        Storage::fake('public');

        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(PressingSettings::class)
            ->set('logo', UploadedFile::fake()->image('logo.jpg'))
            ->call('save');

        $pressing->refresh();
        $this->assertNotNull($pressing->logo_path);
        Storage::disk('public')->assertExists($pressing->logo_path);
    }

    public function test_admin_can_remove_the_logo(): void
    {
        Storage::fake('public');

        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(PressingSettings::class)
            ->set('logo', UploadedFile::fake()->image('logo.jpg'))
            ->call('save');

        $path = $pressing->refresh()->logo_path;

        Livewire::test(PressingSettings::class)->call('removeLogo');

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($pressing->refresh()->logo_path);
    }
}
