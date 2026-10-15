<?php

namespace Tests\Feature;

use App\Livewire\Orders\Create;
use App\Livewire\Pressing\Settings as PressingSettings;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_input_is_normalized_to_the_stored_format(): void
    {
        $this->assertSame('+2250700000000', PhoneNumber::normalize('07 00 00 00 00'));
        $this->assertSame('+2252722411835', PhoneNumber::normalize('+225 27 22 41 18 35'));
        $this->assertSame('+2252722411835', PhoneNumber::normalize('27.22.41.18.35'));
        $this->assertSame('+2250102030405', PhoneNumber::normalize('00225 01 02 03 04 05'));
        $this->assertSame('+2250506070809', PhoneNumber::normalize('225-05-06-07-08-09'));
    }

    public function test_pressing_settings_accept_a_landline_with_spaces(): void
    {
        $user = User::factory()->create();
        $pressing = Pressing::factory()->create(['phone' => '+2250700000000']);
        $pressing->staff()->attach($user, ['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user);

        Livewire::test(PressingSettings::class)
            ->set('phone', '27 22 41 18 35')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('+2252722411835', $pressing->fresh()->phone);
    }

    public function test_invalid_numbers_get_a_french_message(): void
    {
        $user = User::factory()->create();
        $pressing = Pressing::factory()->create();
        $pressing->staff()->attach($user, ['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user);

        Livewire::test(PressingSettings::class)
            ->set('phone', '12345')
            ->call('save')
            ->assertHasErrors(['phone'])
            ->assertSee('Saisissez un numéro ivoirien à 10 chiffres');
    }

    public function test_new_customer_from_the_order_form_accepts_spaces(): void
    {
        $user = User::factory()->create();
        $pressing = Pressing::factory()->create();
        $pressing->staff()->attach($user, ['role' => 'admin', 'is_active' => true]);
        Subscription::factory()->for($pressing)->create();
        $this->actingAs($user);

        Livewire::test(Create::class)
            ->set('newFirstName', 'Mariam')
            ->set('newLastName', 'Bamba')
            ->set('newPhone', '05 44 33 22 11')
            ->call('createAndPickCustomer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', ['phone' => '+2250544332211']);
    }
}
