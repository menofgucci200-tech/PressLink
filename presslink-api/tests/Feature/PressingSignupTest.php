<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Enums\SubscriptionStatus;
use App\Livewire\Auth\PressingSignup;
use App\Mail\PressingSignedUp;
use App\Mail\TrialEndingSoon;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Inscription d'un pressing depuis le site : deux étapes, essai gratuit
 * (fin du lancement + une semaine), gérant connecté, équipe prévenue ;
 * guide de démarrage, bandeau d'essai et rappels de fin d'essai.
 */
class PressingSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-28 10:00:00');
        config(['presslink.launch.free_until' => '2026-11-15']);
        RateLimiter::clear('inscription-pressing:127.0.0.1');
    }

    private function fillForm(): Testable
    {
        return Livewire::test(PressingSignup::class)
            ->set('pressingName', 'Pressing du Plateau')
            ->set('city', 'Cocody Angré')
            ->set('pressingPhone', '27 22 44 55 66')
            ->call('next')
            ->assertSet('step', 2)
            ->set('ownerName', 'Awa Koné')
            ->set('ownerPhone', '07 08 12 44 00')
            ->set('email', 'awa@example.com')
            ->set('password', 'motdepasse1')
            ->set('acceptTerms', true);
    }

    public function test_the_signup_page_announces_the_trial_end_date(): void
    {
        $this->get(route('signup'))->assertOk()
            ->assertSee('Étape 1 sur 2')
            ->assertSee('22 novembre');
    }

    public function test_a_pressing_signs_up_and_lands_on_its_dashboard_with_a_trial(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $this->fillForm()->call('register')->assertRedirect(route('dashboard'));

        $owner = User::where('phone', '+2250708124400')->firstOrFail();
        $pressing = Pressing::where('name', 'Pressing du Plateau')->firstOrFail();
        $this->assertAuthenticatedAs($owner);
        $this->assertSame('Cocody Angré', $pressing->city);
        $this->assertSame('+2252722445566', $pressing->phone);
        $this->assertSame(PressingRole::Admin, $pressing->staff()->firstOrFail()->pivot->role);
        $this->assertSame(SubscriptionStatus::Trialing, $pressing->subscription->status);
        $this->assertSame('2026-11-22', $pressing->subscription->trial_ends_at->toDateString(), 'gratuit jusqu\'au 15 novembre, puis une semaine');
        $this->assertGreaterThan(0, $pressing->prestations()->count(), 'services de base créés');

        Mail::assertSent(PressingSignedUp::class, fn (PressingSignedUp $mail) => $mail->hasTo(config('presslink.support.email')) && $mail->pressing->is($pressing));
    }

    public function test_after_the_launch_the_trial_lasts_a_week_from_the_signup(): void
    {
        $this->travelTo('2026-12-01 10:00:00');

        $this->fillForm()->call('register');

        $this->assertSame('2026-12-08', Pressing::firstOrFail()->subscription->trial_ends_at->toDateString());
    }

    public function test_each_step_checks_its_fields(): void
    {
        Livewire::test(PressingSignup::class)
            ->call('next')
            ->assertHasErrors(['pressingName', 'city', 'pressingPhone'])
            ->assertSet('step', 1);

        $this->fillForm()->set('acceptTerms', false)->set('password', 'court')
            ->call('register')
            ->assertHasErrors(['acceptTerms', 'password']);

        $this->assertSame(0, Pressing::count());
    }

    public function test_a_number_that_already_has_an_account_is_sent_to_the_login(): void
    {
        User::factory()->create(['phone' => '+2250708124400']);

        $this->fillForm()->call('register')
            ->assertHasErrors('ownerPhone')
            ->assertSee('connectez-vous');

        $this->assertSame(0, Pressing::count());
    }

    public function test_robots_and_repeated_signups_are_stopped(): void
    {
        $this->fillForm()->set('website', 'spam.example')->call('register');
        $this->assertSame(0, Pressing::count(), 'le champ piège bloque les robots');

        foreach (range(1, PressingSignup::MAX_PER_HOUR) as $i) {
            $this->fillForm()->set('ownerPhone', '07 08 12 44 0'.$i)->set('email', '')->call('register');
            auth()->logout();
        }
        $this->fillForm()->set('ownerPhone', '07 08 12 44 09')->set('email', '')->call('register')->assertHasErrors('ownerPhone');

        $this->assertSame(PressingSignup::MAX_PER_HOUR, Pressing::count());
    }

    public function test_the_site_buttons_lead_to_the_signup_page(): void
    {
        $this->get('/')->assertOk()->assertSee(route('signup'), false);
        $this->get(route('site.pricing'))->assertOk()->assertSee(route('signup'), false);
        $this->get(route('login'))->assertOk()->assertSee(route('signup'), false);
    }

    public function test_a_new_pressing_sees_its_start_guide_and_its_trial_banner(): void
    {
        $this->fillForm()->call('register');

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Bien démarrer avec PressLink')
            ->assertSee('Ajoutez vos articles et vos prix')
            ->assertSee("Essai gratuit : encore 26 jours, jusqu'au 22/11/2026", false)
            ->assertSee('Choisir ma formule');
    }

    public function test_owners_are_reminded_three_days_and_one_day_before_the_end_of_the_trial(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->fillForm()->call('register');
        $subscription = Subscription::firstOrFail();

        $this->travelTo('2026-11-15 09:00:00');
        $this->artisan('presslink:trial-reminders')->assertSuccessful();
        Mail::assertNotSent(TrialEndingSoon::class);

        $this->travelTo('2026-11-19 09:00:00');
        $this->artisan('presslink:trial-reminders')->assertSuccessful();
        Mail::assertSent(TrialEndingSoon::class, fn (TrialEndingSoon $mail) => $mail->hasTo('awa@example.com') && $mail->daysLeft === 3 && $mail->subscription->is($subscription));

        $this->travelTo('2026-11-21 09:00:00');
        $this->artisan('presslink:trial-reminders')->assertSuccessful();
        Mail::assertSent(TrialEndingSoon::class, fn (TrialEndingSoon $mail) => $mail->daysLeft === 1);
    }

    public function test_each_start_step_opens_its_page_with_a_guided_tour(): void
    {
        $this->fillForm()->call('register');

        $dashboard = $this->get(route('dashboard'))->assertOk()->assertSee('Me guider');
        foreach ([
            'services?guide=prix' => ['ajouter-piece', 'piece-nom', 'piece-prix', 'piece-enregistrer'],
            'parametres?guide=horaires' => ['horaires', 'parametres-enregistrer'],
            'parametres?guide=logo' => ['logo', 'parametres-enregistrer'],
            'equipe?guide=equipe' => ['ajouter-membre'],
            'commandes/nouvelle?guide=commande' => ['commande-client', 'commande-pieces', 'commande-ticket', 'commande-valider'],
        ] as $path => $markers) {
            $dashboard->assertSee(url($path), false);

            // Les éléments que l'accompagnement met en lumière sont bien sur la page
            // (ceux des formulaires apparaissent à l'ouverture du formulaire).
            $page = $this->get(url($path))->assertOk();
            foreach ($markers as $marker) {
                if (in_array($marker, ['piece-nom', 'piece-prix', 'piece-enregistrer'], true)) {
                    continue;
                }
                $page->assertSee('data-guide="'.$marker.'"', false);
            }
        }
    }
}
