<?php

namespace Tests\Feature;

use App\Livewire\Admin\Site\Index as AdminSite;
use App\Models\User;
use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Site vitrine pour les pressings : essai gratuit, réassurance, questions des
 * gérants, guides (référencement local) et témoignages réels uniquement.
 */
class SitePressingsContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pressings_page_offers_the_free_trial_and_answers_managers(): void
    {
        // Après l'offre de lancement : l'essai habituel.
        $this->travelTo('2026-11-20 09:00:00');

        $this->get(route('site.pressings'))
            ->assertOk()
            ->assertSee('Essayer gratuitement 7 jours')
            ->assertSee('en 2 minutes')
            ->assertSee(route('signup'), false)
            ->assertSee('Abidjan')
            ->assertSee('Se lancer sans risque')
            ->assertSee('Faut-il acheter du matériel ?')
            ->assertSee('Mode hors connexion')
            ->assertSee('"applicationCategory":"BusinessApplication"', false)
            ->assertDontSee('Ils utilisent PressLink');
    }

    public function test_the_trial_button_announces_the_launch_offer_while_it_runs(): void
    {
        $this->travelTo('2026-10-26 09:00:00');

        foreach ([route('site.pressings'), route('site.pricing'), '/'] as $url) {
            $this->get($url)->assertOk()
                // Fin du lancement (15 novembre) + la semaine pour choisir sa formule.
                ->assertSee('Essayer gratuitement jusqu&#039;au 22 novembre', false)
                ->assertDontSee('Essayer gratuitement 7 jours');
        }
        $this->get(route('site.pressings'))->assertSee('Gratuit jusqu&#039;au 15 novembre pendant le lancement officiel', false);
    }

    public function test_the_offers_page_and_the_home_page_offer_the_free_trial(): void
    {
        $this->travelTo('2026-11-20 09:00:00');

        $this->get(route('site.pricing'))->assertOk()->assertSee('Essayer gratuitement 7 jours')->assertSee('Y a-t-il un engagement ?')->assertSee('"priceCurrency":"XOF"', false);
        $this->get('/')->assertOk()->assertSee('Essayer gratuitement 7 jours');
    }

    public function test_the_faq_has_a_section_for_managers(): void
    {
        $this->get(route('site.faq'))->assertOk()->assertSee('Vous gérez un pressing')->assertSee('Que se passe-t-il quand Internet coupe ?');
    }

    public function test_the_guides_are_published_and_listed_in_the_sitemap(): void
    {
        $this->get(route('site.guides'))->assertOk()->assertSee('Guides pour les gérants de pressing');

        foreach (Guides::ALL as $slug => $guide) {
            $this->get(route('site.guide', $slug))->assertOk()->assertSee($guide['title'])->assertSee('"@type":"Article"', false);
        }

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/guides/pressing-coupure-internet', $sitemap);
        $this->get('/guides/inconnu')->assertNotFound();
    }

    public function test_testimonials_are_published_only_with_the_manager_consent(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        Livewire::actingAs($admin)->test(AdminSite::class)
            ->set('form', ['name' => 'Awa Koné', 'pressing' => 'Pressing Étoile', 'commune' => 'Cocody', 'quote' => 'Mes clients savent quand leurs habits sont prêts, je ne passe plus mes journées au téléphone.', 'consent' => false])
            ->call('add')
            ->assertHasErrors('form.consent')
            ->set('form.consent', true)
            ->call('add')
            ->assertHasNoErrors();

        $this->get(route('site.pressings'))->assertSee('Ils utilisent PressLink')->assertSee('Awa Koné')->assertSee('Pressing Étoile, Cocody');

        $this->actingAs(User::factory()->create())->get(route('admin.site.index'))->assertForbidden();
    }
}
