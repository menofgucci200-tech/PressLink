<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Actions\Sandbox\StartSandbox;
use App\Livewire\Admin\Clients\Index as AdminClientsIndex;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Orders\Index as AdminOrdersIndex;
use App\Livewire\Admin\Pressings\Index as AdminPressingsIndex;
use App\Livewire\Auth\PressingSignup;
use App\Livewire\Auth\SandboxStart;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\SandboxTrial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * « Découvrir avec un pressing fictif » : un visiteur lance un pressing de
 * test déjà rempli, pour quelques jours. Ses données n'entrent dans aucune
 * statistique, ne touchent aucun vrai client, n'envoient rien, et sont
 * effacées à l'expiration ; il peut créer son vrai compte en gardant ses
 * articles et ses prix.
 */
class SandboxPressingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('test-pressing:127.0.0.1');
        RateLimiter::clear('inscription-pressing:127.0.0.1');
    }

    /** @return array{manager: User, trial: SandboxTrial, pressing: Pressing} */
    private function startSandbox(string $name = 'Pressing Élégance'): array
    {
        $result = (new StartSandbox)->handle($name, '+2250708124400', '127.0.0.1');

        return [...$result, 'pressing' => $result['trial']->pressing];
    }

    public function test_a_visitor_launches_a_filled_sandbox_and_lands_on_its_dashboard(): void
    {
        $this->get(route('sandbox.start'))->assertOk()->assertSee('Tester PressLink')->assertSee('3 jours');

        Livewire::test(SandboxStart::class)
            ->set('pressingName', 'Pressing Élégance')
            ->set('phone', '07 08 12 44 00')
            ->call('start')
            ->assertRedirect(route('dashboard'));

        $trial = SandboxTrial::sole();
        $pressing = $trial->pressing;
        $this->assertSame('Pressing Élégance', $pressing->name);
        $this->assertSame('+2250708124400', $trial->contact_phone);
        $this->assertTrue($pressing->isSandbox());
        $this->assertTrue($pressing->is_demo);
        $this->assertSame(now()->addDays(3)->toDateString(), $pressing->sandbox_expires_at->toDateString());
        $this->assertSame(5, $pressing->services()->count());
        $this->assertSame(4, $pressing->customers()->count());
        $this->assertSame(5, $pressing->orders()->count());
        $this->assertAuthenticatedAs($pressing->admins()->sole());

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('data-testid="sandbox-banner"', false)
            ->assertSee('Créer mon vrai compte');
    }

    public function test_the_start_form_requires_a_name_and_a_whatsapp_number_and_is_rate_limited(): void
    {
        Livewire::test(SandboxStart::class)
            ->call('start')
            ->assertHasErrors(['pressingName' => 'required', 'phone' => 'required']);

        for ($i = 0; $i < SandboxStart::MAX_PER_HOUR; $i++) {
            Livewire::test(SandboxStart::class)->set('pressingName', "Test {$i}")->set('phone', '0708124400')->call('start');
            auth()->logout();
        }

        Livewire::test(SandboxStart::class)->set('pressingName', 'Encore')->set('phone', '0708124400')->call('start')
            ->assertHasErrors('phone');
        $this->assertSame(SandboxStart::MAX_PER_HOUR, SandboxTrial::count());
    }

    public function test_a_visitor_resumes_the_running_test_remembered_by_the_browser(): void
    {
        ['trial' => $trial, 'manager' => $manager] = $this->startSandbox();

        Livewire::withCookie(SandboxStart::COOKIE, (string) $trial->id)
            ->test(SandboxStart::class)
            ->assertSee('Reprendre mon test')
            ->call('resume')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($manager);
    }

    public function test_sandbox_data_stays_out_of_the_super_admin_figures_and_lists(): void
    {
        $real = Pressing::factory()->create(['name' => 'Vrai Pressing']);
        $customer = Customer::factory()->create();
        $real->customers()->attach($customer, ['joined_at' => now()]);
        (new CreateOrderAction)->handle($real, $customer, [['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1]]);
        $this->startSandbox('Pressing Fictif');

        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        Livewire::test(AdminDashboard::class)->set('period', 'all')
            ->assertViewHas('activePressings', 1)
            ->assertViewHas('totalClients', 1)
            ->assertViewHas('totalOrders', 1)
            ->assertSee('Tests découverte')
            ->assertSee('Pressing Fictif');
        Livewire::test(AdminPressingsIndex::class)->assertSee('Vrai Pressing')->assertDontSee('Pressing Fictif');
        Livewire::test(AdminOrdersIndex::class)->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
        Livewire::test(AdminClientsIndex::class)
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 1)
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 1);
    }

    public function test_sandbox_customers_never_touch_real_customers(): void
    {
        $realCustomer = Customer::factory()->create(['phone' => '+2250707070707', 'first_name' => 'Vrai', 'password' => 'secret1234']);
        ['manager' => $manager, 'pressing' => $sandbox] = $this->startSandbox();
        $this->actingAs($manager);

        // Le numéro d'un vrai client saisi dans le test crée une fiche fictive à part.
        Livewire::test(ClientsIndex::class)
            ->set('phone', '07 07 07 07 07')
            ->set('firstName', 'Fictif')
            ->set('lastName', 'Client')
            ->call('createClient')
            ->assertSet('knownCustomerId', null);

        $fake = Customer::where('sandbox_pressing_id', $sandbox->id)->where('phone', '+2250707070707')->sole();
        $this->assertSame('Fictif', $fake->first_name);
        $this->assertFalse($realCustomer->pressings()->whereKey($sandbox->id)->exists());

        // L'application client ne retrouve que le vrai compte, et ne peut pas rejoindre un pressing de test.
        $this->postJson('/api/v1/auth/customer/login', ['phone' => '+2250707070707', 'password' => 'secret1234'])->assertOk()
            ->assertJsonPath('customer.first_name', 'Vrai');
        $this->assertNull(Pressing::findByPublicCode($sandbox->code));
    }

    public function test_nothing_is_sent_to_the_customers_of_a_sandbox(): void
    {
        ['pressing' => $sandbox] = $this->startSandbox();
        Notification::fake();

        $order = (new CreateOrderAction)->handle($sandbox, $sandbox->customers()->first(), [['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1]]);
        $order->update(['status' => 'traitement']);
        $order->update(['status' => 'prete']);

        Notification::assertNothingSent();
    }

    public function test_subscription_pages_lead_to_the_real_account_creation(): void
    {
        ['manager' => $manager] = $this->startSandbox();

        $this->actingAs($manager)->get(route('subscription.plans'))->assertRedirect(route('sandbox.convert'));
        $this->get(route('subscription.show'))->assertRedirect(route('sandbox.convert'));
    }

    public function test_an_expired_sandbox_logs_the_visitor_out_and_is_purged_with_its_data(): void
    {
        ['manager' => $manager, 'pressing' => $sandbox, 'trial' => $trial] = $this->startSandbox();

        $this->travel(StartSandbox::DAYS)->days();
        $this->travel(1)->minutes();

        $this->actingAs($manager)->get(route('dashboard'))->assertRedirect(route('sandbox.start'));
        $this->assertGuest();
        $this->get(route('sandbox.start'))->assertSee('Votre test est terminé');

        $this->artisan('presslink:purge-sandboxes')->assertSuccessful();

        $this->assertModelMissing($sandbox);
        $this->assertModelMissing($manager);
        $this->assertSame(0, Customer::where('sandbox_pressing_id', $sandbox->id)->count());
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('Pressing Élégance', $trial->fresh()->pressing_name, 'la fiche du test reste pour le suivi');
        $this->assertSame('Terminé', $trial->fresh()->statusLabel());
    }

    public function test_the_visitor_creates_the_real_account_keeping_articles_and_prices(): void
    {
        ['manager' => $manager, 'pressing' => $sandbox, 'trial' => $trial] = $this->startSandbox();
        $shirt = $sandbox->services()->where('name', 'Chemise')->sole();
        $shirt->setPrice($sandbox->prestations()->where('icon', 'nettoyage')->sole(), 1250);
        $sandbox->services()->create(['name' => 'Boubou', 'icon' => 'robe', 'price_fcfa' => 0, 'is_active' => true])
            ->setPrice($sandbox->prestations()->where('icon', 'repassage')->sole(), 2500);

        $this->actingAs($manager);

        Livewire::test(PressingSignup::class)
            ->assertSet('pressingName', 'Pressing Élégance')
            ->assertSet('pressingPhone', '0708124400')
            ->assertSee('articles et vos prix')
            ->set('city', 'Cocody Angré')
            ->call('next')
            ->set('ownerName', 'Awa Koné')
            ->set('password', 'motdepasse1')
            ->set('acceptTerms', true)
            ->call('register')
            ->assertRedirect(route('dashboard'));

        $owner = User::where('phone', '+2250708124400')->sole();
        $pressing = $owner->pressings()->sole();
        $this->assertAuthenticatedAs($owner);
        $this->assertFalse($pressing->isSandbox());
        $this->assertFalse($pressing->is_demo);
        $this->assertSame(6, $pressing->services()->count());
        $nettoyage = $pressing->prestations()->where('icon', 'nettoyage')->sole();
        $repassage = $pressing->prestations()->where('icon', 'repassage')->sole();
        $this->assertSame(1250, $pressing->services()->where('name', 'Chemise')->sole()->priceFor($nettoyage));
        $this->assertSame(2500, $pressing->services()->where('name', 'Boubou')->sole()->priceFor($repassage));
        $this->assertSame(0, $pressing->customers()->count(), 'les clients fictifs ne suivent pas');
        $this->assertSame(0, $pressing->orders()->count(), 'les commandes fictives ne suivent pas');

        $this->assertModelMissing($sandbox);
        $this->assertTrue($trial->fresh()->isConverted());
        $this->assertTrue($trial->fresh()->convertedPressing->is($pressing));
    }

    public function test_the_conversion_page_is_only_for_sandbox_managers(): void
    {
        $pressing = Pressing::factory()->create();
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)->get(route('sandbox.convert'))->assertRedirect(route('dashboard'));
    }
}
