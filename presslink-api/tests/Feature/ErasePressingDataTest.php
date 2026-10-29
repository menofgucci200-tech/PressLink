<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Actions\Pressings\ErasePressingData;
use App\Enums\ActivityAction;
use App\Livewire\Admin\Pressings\Show;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Console Super Admin : effacer une partie des données d'un pressing
 * (commandes, clients, caisse, articles, journal) sans supprimer le
 * pressing, par exemple les données d'essai saisies avant le vrai démarrage.
 */
class ErasePressingDataTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create(['code' => 'AB-1234']);
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
    }

    private function customerWithOrder(?Pressing $alsoIn = null, ?string $password = null): Customer
    {
        $customer = Customer::factory()->create(['password' => $password]);
        $this->pressing->customers()->attach($customer, ['joined_at' => now()]);
        $alsoIn?->customers()->attach($customer, ['joined_at' => now()]);
        (new CreateOrderAction)->handle($this->pressing, $customer, [['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1]]);

        return $customer;
    }

    public function test_the_super_admin_erases_test_orders_and_customers_but_keeps_the_pressing(): void
    {
        $other = Pressing::factory()->create();
        $testOnly = $this->customerWithOrder();
        $sharedCustomer = $this->customerWithOrder(alsoIn: $other);
        $appCustomer = $this->customerWithOrder(password: 'secret1234');
        $this->pressing->services()->create(['name' => 'Chemise', 'icon' => 'chemise', 'price_fcfa' => 1000, 'is_active' => true]);

        Livewire::test(Show::class, ['pressing' => $this->pressing])
            ->set('eraseParts', [ErasePressingData::CUSTOMERS])
            ->set('eraseConfirmation', 'AB-1234')
            ->call('eraseData')
            ->assertHasNoErrors();

        $this->assertModelExists($this->pressing);
        $this->assertSame(0, $this->pressing->orders()->count(), 'effacer les clients efface aussi leurs commandes');
        $this->assertSame(0, $this->pressing->customers()->count());
        $this->assertSame(1, $this->pressing->services()->count(), 'les articles restent');
        $this->assertModelMissing($testOnly);
        $this->assertModelExists($sharedCustomer);
        $this->assertTrue($other->customers()->whereKey($sharedCustomer->id)->exists());
        $this->assertModelExists($appCustomer);

        $log = ActivityLog::where('pressing_id', $this->pressing->id)->where('action', ActivityAction::DataErased)->sole();
        $this->assertStringContainsString('commandes (3)', $log->description);
        $this->assertStringContainsString('clients (3)', $log->description);
    }

    public function test_it_erases_only_the_chosen_parts(): void
    {
        $this->customerWithOrder();
        $this->pressing->services()->create(['name' => 'Chemise', 'icon' => 'chemise', 'price_fcfa' => 1000, 'is_active' => true]);

        Livewire::test(Show::class, ['pressing' => $this->pressing])
            ->set('eraseParts', [ErasePressingData::CATALOG])
            ->set('eraseConfirmation', 'AB-1234')
            ->call('eraseData');

        $this->assertSame(0, $this->pressing->services()->count());
        $this->assertSame(1, $this->pressing->orders()->count());
        $this->assertSame(1, $this->pressing->customers()->count());
    }

    public function test_erasing_requires_a_choice_and_the_pressing_code(): void
    {
        $this->customerWithOrder();

        Livewire::test(Show::class, ['pressing' => $this->pressing])
            ->set('eraseConfirmation', 'AB-1234')
            ->call('eraseData')
            ->assertHasErrors('eraseParts')
            ->set('eraseParts', [ErasePressingData::ORDERS])
            ->set('eraseConfirmation', 'mauvais')
            ->call('eraseData')
            ->assertHasErrors('eraseConfirmation');

        $this->assertSame(1, $this->pressing->orders()->count());
    }

    public function test_only_the_super_admin_can_erase_data(): void
    {
        $owner = User::factory()->create();
        $this->pressing->staff()->attach($owner, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($owner);
        Livewire::test(Show::class, ['pressing' => $this->pressing])->assertForbidden();
    }
}
