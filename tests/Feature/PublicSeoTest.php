<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    public function test_structured_data_renders_a_valid_schema_context_instead_of_a_blade_directive(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $schema = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org', $schema['@context'] ?? null);
        $this->assertNotEmpty($schema['@graph']);
    }

    public function test_homepage_is_a_public_search_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Online Paper Generator Pakistan for Schools &amp; Academies | Exam Builder', false)
            ->assertSee('<link rel="canonical" href="https://exambuilder.algorahub.com/">', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('property="og:image:width" content="1672"', false)
            ->assertSee('property="og:image:height" content="940"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('Made for teachers and schools in Pakistan');
    }

    public function test_crawlers_can_discover_the_sitemap_and_ai_summary(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        $sitemap = file_get_contents(public_path('sitemap.xml'));
        $llms = file_get_contents(public_path('llms.txt'));

        $this->assertIsString($robots);
        $this->assertStringContainsString('Sitemap: https://exambuilder.algorahub.com/sitemap.xml', $robots);
        $this->assertStringContainsString('User-agent: GPTBot', $robots);
        $this->assertStringContainsString('<loc>https://exambuilder.algorahub.com/</loc>', $sitemap);
        $this->assertStringContainsString('teachers and schools in Pakistan', $llms);
    }
}
