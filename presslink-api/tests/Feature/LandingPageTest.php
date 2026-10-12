<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Référencement (SEO) de presslink.org : page d'accueil publique,
 * métadonnées, données structurées, sitemap et pages privées en noindex.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_public_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Suivez vos vêtements au pressing', false)
            ->assertSee('presslink.org/app', false)
            ->assertSee(route('login'), false);
    }

    public function test_landing_page_exposes_complete_seo_metadata(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/<title>(.*?)<\/title>/u', $html, $title);
        $titleText = html_entity_decode($title[1] ?? '', ENT_QUOTES);
        $this->assertGreaterThanOrEqual(30, mb_strlen($titleText));
        $this->assertLessThanOrEqual(60, mb_strlen($titleText), 'Google tronque les titles au-delà de ~60 caractères');
        $this->assertStringContainsString('pressing', mb_strtolower($titleText));
        $this->assertStringContainsString('<meta name="description"', $html);
        preg_match('/<meta name="description" content="(.*?)">/u', $html, $description);
        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($description[1] ?? '', ENT_QUOTES)), 'Google tronque les descriptions au-delà de ~160 caractères');
        $this->assertStringContainsString('<meta name="keywords" content="pressing Abidjan', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow', $html);
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.rtrim(config('app.url'), '/').'/og-image.png">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'un seul h1');
    }

    public function test_landing_page_json_ld_is_valid_and_faq_matches_visible_content(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $data = json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR);

        $types = collect($data['@graph'])->pluck('@type')->all();
        $this->assertEqualsCanonicalizing(['Organization', 'WebSite', 'SoftwareApplication', 'FAQPage'], $types);

        $faq = collect($data['@graph'])->firstWhere('@type', 'FAQPage');
        $this->assertGreaterThanOrEqual(5, count($faq['mainEntity']));

        foreach ($faq['mainEntity'] as $question) {
            $this->assertStringContainsString(e($question['name']), $html, 'chaque question JSON-LD est visible sur la page');
        }
    }

    public function test_authenticated_staff_are_redirected_to_their_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_super_admins_are_redirected_to_the_admin_area(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $this->get('/')->assertRedirect('/admin');
    }

    public function test_sitemap_lists_only_the_public_pages(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $xml = simplexml_load_string($response->getContent());
        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = (string) $url->loc;
        }

        $base = rtrim(config('app.url'), '/');
        $this->assertSame([$base.'/', $base.'/app/'], $locs);
    }

    public function test_private_pages_are_not_indexable(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_robots_txt_blocks_private_areas_and_points_to_the_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://presslink.org/sitemap.xml', $robots);
        foreach (['/login', '/admin', '/api/', '/tableau-de-bord'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}", $robots);
        }
    }
}
