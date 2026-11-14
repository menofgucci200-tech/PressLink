<?php

namespace Tests\Feature;

use App\Livewire\Admin\Audience\Index as AdminAudience;
use App\Models\AudienceEvent;
use App\Models\Pressing;
use App\Models\SandboxTrial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Audience du site vitrine : pages vues et clics reçus du site, sans cookie
 * ni adresse IP gardée, et le menu Audience de la console super admin.
 */
class AudienceTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 14; SM-A155F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';

    private const COMPUTER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    /** @param  array<string, mixed>  $payload */
    private function collect(array $payload, string $userAgent = self::PHONE, string $ip = '196.47.1.10'): TestResponse
    {
        return $this->withHeaders(['User-Agent' => $userAgent])
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson(route('audience.store'), $payload);
    }

    private function event(array $attributes = []): AudienceEvent
    {
        return AudienceEvent::create([
            'type' => AudienceEvent::PAGE,
            'path' => '/',
            'device' => 'mobile',
            'visitor' => 'v1',
            'is_entry' => true,
            'occurred_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_public_pages_and_signup_carry_the_audience_tag(): void
    {
        $tag = '<meta name="presslink-audience" content="'.route('audience.store').'">';

        $this->get(route('site.pricing'))->assertOk()->assertSee($tag, false);
        $this->get(route('signup'))->assertOk()->assertSee($tag, false);
        $this->get(route('login'))->assertOk()->assertDontSee('presslink-audience', false);
    }

    public function test_a_page_view_is_stored_without_cookie_nor_ip_address(): void
    {
        $response = $this->collect(['type' => 'page', 'path' => '/offres?code=secret', 'referrer' => 'https://www.google.com/search?q=pressing']);

        $response->assertNoContent();
        $this->assertSame([], $response->headers->getCookies());

        $event = AudienceEvent::sole();
        $this->assertSame('/offres', $event->path);
        $this->assertSame('Google', $event->source);
        $this->assertTrue($event->is_entry);
        $this->assertSame('mobile', $event->device);
        $this->assertSame(AudienceEvent::visitorFingerprint('196.47.1.10', self::PHONE), $event->visitor);
        $this->assertStringNotContainsString('196.47', json_encode($event->getAttributes()));
    }

    public function test_campaign_wins_over_referrer_and_internal_navigation_is_not_an_arrival(): void
    {
        $this->collect(['type' => 'page', 'path' => '/', 'referrer' => 'https://l.facebook.com/', 'campaign' => 'Affiche']);
        $this->collect(['type' => 'page', 'path' => '/offres', 'referrer' => 'http://localhost/']);
        $this->collect(['type' => 'page', 'path' => '/contact']);

        $this->assertSame(['affiche', null, null], AudienceEvent::orderBy('id')->pluck('source')->all());
        $this->assertSame([true, false, true], AudienceEvent::orderBy('id')->pluck('is_entry')->all());
    }

    public function test_a_click_keeps_its_label_and_destination(): void
    {
        $this->collect(['type' => 'click', 'path' => '/', 'label' => "  Créer mon\n compte  ", 'target' => '/inscription'], self::COMPUTER)->assertNoContent();

        $event = AudienceEvent::sole();
        $this->assertSame('Créer mon compte', $event->label);
        $this->assertSame('/inscription', $event->target);
        $this->assertSame('ordinateur', $event->device);
        $this->assertFalse($event->is_entry);
    }

    public function test_robots_and_invalid_payloads_are_not_counted(): void
    {
        $this->collect(['type' => 'page', 'path' => '/'], 'Mozilla/5.0 (compatible; Googlebot/2.1)')->assertNoContent();
        $this->collect(['type' => 'page', 'path' => '/'], 'WhatsApp/2.23.20.0')->assertNoContent();
        $this->collect(['type' => 'page', 'path' => 'https://ailleurs.com'])->assertUnprocessable();
        $this->collect(['type' => 'scroll', 'path' => '/'])->assertUnprocessable();

        $this->assertSame(0, AudienceEvent::count());
    }

    public function test_the_visitor_fingerprint_changes_every_day(): void
    {
        $today = AudienceEvent::visitorFingerprint('196.47.1.10', self::PHONE);

        $this->assertSame($today, AudienceEvent::visitorFingerprint('196.47.1.10', self::PHONE));
        $this->assertNotSame($today, AudienceEvent::visitorFingerprint('196.47.1.10', self::PHONE, now()->addDay()));
        $this->assertSame(16, strlen($today));
    }

    public function test_only_super_admins_open_the_audience_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.audience.index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get(route('admin.audience.index'))
            ->assertOk()
            ->assertSee('Audience du site')
            ->assertSee('Aucune visite enregistrée');
    }

    public function test_the_audience_page_sums_visitors_visits_pages_and_clicks(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        // Visiteur 1 : arrive par Google, voit deux pages, clique une fois.
        $this->event(['visitor' => 'a', 'path' => '/', 'source' => 'Google']);
        $this->event(['visitor' => 'a', 'path' => '/inscription', 'is_entry' => false]);
        $this->event(['visitor' => 'a', 'type' => AudienceEvent::CLICK, 'label' => 'Créer mon compte', 'target' => '/inscription', 'is_entry' => false]);
        // Visiteur 2 : accès direct, sur ordinateur.
        $this->event(['visitor' => 'b', 'path' => '/offres', 'device' => 'ordinateur']);
        // Hier, puis il y a 40 jours (hors des 30 jours).
        $this->event(['visitor' => 'a', 'occurred_at' => now()->subDay()]);
        $this->event(['visitor' => 'z', 'occurred_at' => now()->subDays(40)]);

        Pressing::factory()->create();
        SandboxTrial::create(['pressing_name' => 'Test', 'contact_phone' => '+2250700000001', 'expires_at' => now()->addDays(3)]);

        Livewire::test(AdminAudience::class)
            ->assertViewHas('totals', ['visitors' => 3, 'visits' => 3, 'pageviews' => 4, 'clicks' => 1])
            ->assertViewHas('liveVisitors', 2)
            ->assertViewHas('funnel', fn (array $funnel) => $funnel['signup'] === ['views' => 1, 'done' => 1] && $funnel['sandbox'] === ['views' => 0, 'done' => 1])
            // Sans origine (clé vide) : accès direct ou lien partagé.
            ->assertViewHas('sources', fn ($sources) => $sources->pluck('visits', 'source')->all() === ['' => 2, 'Google' => 1])
            ->assertSee('Créer mon compte')
            ->assertSee('Inscription d\'un pressing')
            ->set('period', 'today')
            ->assertViewHas('totals', ['visitors' => 2, 'visits' => 2, 'pageviews' => 3, 'clicks' => 1]);
    }

    public function test_the_admin_menu_links_to_the_audience_page(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.audience.index'), false);
    }

    public function test_audience_older_than_twelve_months_is_purged(): void
    {
        $this->event(['occurred_at' => now()->subMonths(13)]);
        $kept = $this->event(['occurred_at' => now()->subMonths(11)]);

        $this->artisan('presslink:purge-audience')->assertSuccessful();

        $this->assertSame([$kept->id], AudienceEvent::pluck('id')->all());
    }
}
