<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    public function test_homepage_is_a_public_search_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('<title>Exam Builder Pakistan | Create School Exams and Question Papers</title>', false)
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
