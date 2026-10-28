<?php

namespace Tests\Feature;

use App\Support\PublicSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_homepages_keep_visible_prices_consistent_with_configured_offers(): void
    {
        DB::table('subscription_plans')->where('key', 'monthly')->update(['amount' => 900]);
        DB::table('subscription_plans')->where('key', 'quarterly')->update(['amount' => 2400]);
        foreach (['/', '/ur', '/pricing', '/ur/pricing'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $text = strip_tags(preg_replace('/<script\\b[^>]*>.*?<\\/script>/is', '', $html));
            $this->assertStringContainsString('900', $text, $path);
            $this->assertStringContainsString('2,400', $text, $path);
            preg_match('/<script type="application\\/ld\\+json">\\s*(.*?)\\s*<\\/script>/s', $html, $match);
            $schema = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
            $app = collect($schema['@graph'])->firstWhere('@type', 'SoftwareApplication');
            $this->assertSame([0, 900, 2400, 4500], array_column($app['offers'], 'price'));
        }
    }

    private const PATHS = ['', 'for-schools-and-academies', 'ai-question-paper-generator', 'urdu-english-paper-maker', 'pricing', 'guides/how-to-create-question-paper', 'about', 'contact'];

    public function test_all_public_pages_have_unique_titles_canonical_alternates_and_readable_content(): void
    {
        $titles = [];
        foreach (['en', 'ur'] as $locale) {
            foreach (self::PATHS as $path) {
                $url = $locale === 'ur' ? '/ur'.($path ? '/'.$path : '') : '/'.$path;
                $html = $this->get($url)->assertOk()->getContent();
                preg_match('/<title>(.*?)<\/title>/s', $html, $match);
                $this->assertNotEmpty($match[1] ?? null, $url);
                $titles[] = $match[1];
                $this->assertStringContainsString('rel="canonical" href="https://exambuilder.algorahub.com'.$url.'"', $html);
                $this->assertStringContainsString('hreflang="en-PK"', $html);
                $this->assertStringContainsString('hreflang="ur-PK"', $html);
                $englishUrl = 'https://exambuilder.algorahub.com/'.$path;
                $urduUrl = 'https://exambuilder.algorahub.com/ur'.($path ? '/'.$path : '');
                $this->assertStringContainsString('hreflang="en-PK" href="'.$englishUrl.'"', $html);
                $this->assertStringContainsString('hreflang="ur-PK" href="'.$urduUrl.'"', $html);
                $this->assertStringContainsString('<h1', $html);
                $this->assertStringNotContainsString('noindex', $html);
                if ($locale === 'ur') {
                    $this->assertStringContainsString('lang="ur-PK" dir="rtl"', $html);
                }
            }
        }
        $this->assertCount(16, array_unique($titles));
    }

    public function test_pricing_schema_and_visible_content_use_actual_subscription_prices(): void
    {
        $html = $this->get('/pricing')->assertOk()->getContent();
        preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $html, $match);
        $schema = json_decode($match[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org', $schema['@context'] ?? null);
        $app = collect($schema['@graph'])->firstWhere('@type', 'SoftwareApplication');
        $this->assertSame([0, 500, 1350, 4500], array_column($app['offers'], 'price'));
        $this->assertSame(['PKR'], array_values(array_unique(array_column($app['offers'], 'priceCurrency'))));
        $this->assertStringContainsString('03080440058', $html);
        $this->assertStringContainsString('923284177318', $html);
        $this->assertStringContainsString('500', $html);
        $this->assertArrayNotHasKey('aggregateRating', $app);
    }

    public function test_sitemap_lists_all_public_languages_but_no_private_pages(): void
    {
        $xml = simplexml_load_string(file_get_contents(public_path('sitemap.xml')));
        $this->assertNotFalse($xml);
        $xml->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urls = array_map('strval', $xml->xpath('//sm:loc'));
        $this->assertCount(16, $urls);
        $this->assertContains('https://exambuilder.algorahub.com/ur/pricing', $urls);
        foreach ($urls as $url) {
            $this->assertStringNotContainsString('/dashboard', $url);
            $this->assertStringNotContainsString('/register', $url);
            $this->assertStringNotContainsString('/super-admin', $url);
        }
    }

    public function test_unknown_public_urls_do_not_resolve_as_generic_marketing_pages(): void
    {
        $this->get('/ur/not-a-page')->assertNotFound();
        $this->get('/not-a-page')->assertNotFound();
        $this->get('/login')->assertOk()->assertSee('noindex, nofollow, noarchive', false);
    }

    public function test_canonical_origin_is_independent_of_the_local_app_url(): void
    {
        config(['seo.url' => 'https://papers.example.org', 'app.url' => 'http://localhost:8000']);
        $this->get('/ur/contact')->assertOk()
            ->assertSee('rel="canonical" href="https://papers.example.org/ur/contact"', false)
            ->assertSee('hreflang="en-PK" href="https://papers.example.org/contact"', false);
        $xml = simplexml_load_string(app(PublicSite::class)->sitemap());
        $this->assertSame('https://papers.example.org/', (string) $xml->url[0]->loc);
    }
}
