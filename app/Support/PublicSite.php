<?php

namespace App\Support;

final class PublicSite
{
    public const PATHS = [
        'home' => '', 'schools' => 'for-schools-and-academies',
        'ai' => 'ai-question-paper-generator', 'languages' => 'urdu-english-paper-maker',
        'pricing' => 'pricing', 'guide' => 'guides/how-to-create-question-paper',
        'about' => 'about', 'contact' => 'contact',
    ];

    public function content(string $locale): array
    {
        $copy = json_decode(file_get_contents(resource_path('content/public/'.$locale.'.json')), true, 512, JSON_THROW_ON_ERROR);
        $days = SubscriptionCatalog::trialDays();
        // Substitute decoded strings so plan names cannot break content JSON.
        array_walk_recursive($copy, function (&$value) use ($days) {
            if (is_string($value)) {
                $value = str_replace(':trial_days', (string) $days, $value);
            }
        });

        return $copy;
    }

    public function path(string $key, string $locale = 'en'): string
    {
        $slug = self::PATHS[$key];

        return $locale === 'ur' ? '/ur'.($slug ? '/'.$slug : '') : '/'.$slug;
    }

    public function url(string $key, string $locale = 'en'): string
    {
        return config('seo.url').$this->path($key, $locale);
    }

    public function metadata(string $key, string $locale, array $page): array
    {
        $root = $this->url('home');
        $url = $this->url($key, $locale);
        $language = $locale.'-PK';
        $offers = [['@type' => 'Offer', 'name' => SubscriptionCatalog::trialDays().'-day free trial', 'price' => 0, 'priceCurrency' => 'PKR', 'url' => $this->url('pricing', $locale)]];
        foreach (SubscriptionCatalog::active() as $plan) {
            $offers[] = ['@type' => 'Offer', 'name' => $plan['label'].' of full school access', 'price' => $plan['amount'], 'priceCurrency' => 'PKR', 'url' => $this->url('pricing', $locale)];
        }
        $graph = [
            ['@type' => 'Organization', '@id' => $root.'#organization', 'name' => 'Exam Builder', 'url' => $root, 'logo' => config('seo.url').'/logo.png', 'areaServed' => ['@type' => 'Country', 'name' => 'Pakistan']],
            ['@type' => 'WebSite', '@id' => $root.'#website', 'url' => $root, 'name' => 'Exam Builder', 'inLanguage' => ['en-PK', 'ur-PK'], 'publisher' => ['@id' => $root.'#organization']],
            ['@type' => 'WebPage', '@id' => $url.'#webpage', 'url' => $url, 'name' => $page['title'], 'description' => $page['description'], 'inLanguage' => $language, 'isPartOf' => ['@id' => $root.'#website']],
            ['@type' => 'SoftwareApplication', '@id' => $root.'#software', 'name' => 'Exam Builder', 'url' => $root, 'applicationCategory' => 'EducationalApplication', 'operatingSystem' => 'Web browser', 'description' => 'Question paper creation software for schools and academies in Pakistan, to organise school-added questions and create printable papers.', 'offers' => $offers, 'publisher' => ['@id' => $root.'#organization']],
        ];
        if ($key !== 'home') {
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => $locale === 'ur' ? 'صفحۂ اول' : 'Home', 'item' => $this->url('home', $locale)],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $page['nav'], 'item' => $url],
            ]];
        }

        return ['title' => $page['title'], 'description' => $page['description'], 'canonical' => $url,
            'alternates' => ['en-PK' => $this->url($key), 'ur-PK' => $this->url($key, 'ur'), 'x-default' => $this->url($key)],
            'image' => config('seo.url').'/Exam%20Builder%20Education%20Dashboard.png',
            'schema' => ['@context' => 'https://schema.org', '@graph' => $graph]];
    }

    public function sitemap(): string
    {
        $escape = fn ($value) => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach (array_keys(self::PATHS) as $key) {
            foreach (['en', 'ur'] as $locale) {
                $xml .= '  <url><loc>'.$escape($this->url($key, $locale)).'</loc>';
                foreach (['en-PK' => 'en', 'ur-PK' => 'ur', 'x-default' => 'en'] as $language => $target) {
                    $xml .= '<xhtml:link rel="alternate" hreflang="'.$language.'" href="'.$escape($this->url($key, $target)).'"/>';
                }
                $xml .= "</url>\n";
            }
        }

        return $xml."</urlset>\n";
    }
}
