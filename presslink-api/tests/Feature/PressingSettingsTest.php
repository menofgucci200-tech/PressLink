<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PressingRole;
use App\Livewire\Pressing\Settings as PressingSettings;
use App\Models\Customer;
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
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        Livewire::test(PressingSettings::class)
            ->set('openingHours.lundi.closed', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

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

    public function test_the_pressing_contact_is_saved_and_shared_with_customers(): void
    {
        $pressing = Pressing::factory()->create(['phone' => '+2252722411835', 'email' => 'contact@elegance.ci']);
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $this->actingAs($admin);

        // Sans contact renseigné : le téléphone et l'e-mail principaux.
        $this->assertSame(['phone' => '+2252722411835', 'whatsapp' => null, 'email' => 'contact@elegance.ci', 'hours' => null], $pressing->supportContact());

        Livewire::test(PressingSettings::class)
            ->set('supportPhone', '07 08 12 44 00')
            ->set('supportWhatsapp', '05 06 07 08 09')
            ->set('supportHours', 'Du lundi au samedi, 8 h – 19 h')
            ->call('save')
            ->assertHasNoErrors();

        $contact = $pressing->fresh()->supportContact();
        $this->assertSame('+2250708124400', $contact['phone']);
        $this->assertSame('+2250506070809', $contact['whatsapp']);
        $this->assertSame('Du lundi au samedi, 8 h – 19 h', $contact['hours']);

        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Robe', 'unit_price_fcfa' => 2000, 'quantity' => 1],
        ]);
        $token = $customer->createToken('app')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/pressings/mine')
            ->assertJsonPath('0.contact.whatsapp', '+2250506070809');
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/orders/{$order->id}")
            ->assertJsonPath('pressing_contact.phone', '+2250708124400');
    }
}
