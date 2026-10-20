<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Auth\Login;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Pressing\Profile as PressingProfile;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un seul format de numéro (+225 + 10 chiffres) dans le tableau de bord :
 * ce que le personnel tape est remis au format, le reste est refusé.
 */
class IvorianPhoneFormatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Pressing $pressing): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return $user;
    }

    public function test_a_client_number_typed_by_pairs_is_stored_in_the_single_format(): void
    {
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->admin($pressing));

        Livewire::test(OrdersCreate::class)
            ->set('newFirstName', 'Awa')
            ->set('newLastName', 'Konan')
            ->set('newPhone', '07 01 02 03 05')
            ->call('createAndPickCustomer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', ['phone' => '+2250701020305', 'first_name' => 'Awa']);
    }

    public function test_a_client_number_without_exactly_ten_digits_is_refused(): void
    {
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->admin($pressing));

        foreach (['07 01 02 03 0', '07 01 02 03 05 6', '03 01 02 03 05'] as $phone) {
            Livewire::test(OrdersCreate::class)
                ->set('newFirstName', 'Awa')
                ->set('newLastName', 'Konan')
                ->set('newPhone', $phone)
                ->call('createAndPickCustomer')
                ->assertHasErrors('newPhone');
        }

        $this->assertSame(0, Customer::count());
    }

    public function test_a_pressing_can_give_a_landline(): void
    {
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->admin($pressing));

        Livewire::test(PressingProfile::class)
            ->set('phone', '27 22 44 55 66')
            ->call('save')
            ->assertHasNoErrors('phone');

        $this->assertSame('+2252722445566', $pressing->fresh()->phone);
    }

    public function test_staff_can_log_in_with_their_number_typed_by_pairs(): void
    {
        User::factory()->create(['phone' => '+2250708124400', 'login' => null, 'password' => Hash::make('secret123')]);

        Livewire::test(Login::class)
            ->set('login', '07 08 12 44 00')
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }
}
