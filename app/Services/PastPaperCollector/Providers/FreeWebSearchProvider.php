<?php

namespace App\Services\PastPaperCollector\Providers;

use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Models\AISetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Free crawl/search: LangSearch first, then self-hosted SearXNG.
 * Groq browser_search is never used here — it burns the free-tier RPM before generate.
 */
class FreeWebSearchProvider implements WebSearchProvider
{
    public function name(): string
    {
        if (filled($this->langsearchKey())) {
            return 'langsearch';
        }
        if (filled($this->searxngUrl())) {
            return 'searxng';
        }

        return 'none';
    }

    public function isConfigured(): bool
    {
        return filled($this->langsearchKey()) || filled($this->searxngUrl());
    }

    public function search(string $query, int $maxResults = 10, array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'Free web search is not configured. Add a LangSearch API key or SearXNG URL in AI Settings.'
            );
        }

        $maxResults = max(1, min(10, $maxResults));
        $timeout = max(10, (int) ($options['timeout'] ?? AISetting::current()->search_timeout ?? 20));
        $query = $this->normalizeQuery($query);

        if (filled($this->langsearchKey())) {
            return $this->searchLangsearch($query, $maxResults, $options, $timeout);
        }

        return $this->searchSearxng($query, $maxResults, $options, $timeout);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array{title:string,url:string,snippet:?string}>
     */
    protected function searchLangsearch(string $query, int $maxResults, array $options, int $timeout): array
    {
        $settings = AISetting::current();
        $settings->applyProviderConfig();

        $url = rtrim((string) config('ai.web_search.langsearch_url', 'https://api.langsearch.com/v1/web-search'), '/');
        $payload = [
            'query' => $query,
            'count' => $maxResults,
            'freshness' => 'noLimit',
            'contents' => ['text' => false],
        ];

        $domains = $this->includeDomains($options);
        if ($domains !== []) {
            $payload['includeDomains'] = $domains;
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->withToken((string) $this->langsearchKey())
            ->post($url, $payload);

        if ($response->status() === 429) {
            throw new \RuntimeException('LangSearch rate limit exceeded. Wait and retry.');
        }

        if (! $response->successful()) {
            throw new \RuntimeException(
                'LangSearch request failed (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 400)
            );
        }

        return $this->uniqueResults($this->parseLangsearch($response->json()), $maxResults);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array{title:string,url:string,snippet:?string}>
     */
    protected function searchSearxng(string $query, int $maxResults, array $options, int $timeout): array
    {
        $base = $this->searxngUrl();
        $domains = $this->includeDomains($options);
        if ($domains !== []) {
            $query = 'site:'.$domains[0].' '.$query;
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'ExamBuilderTeacherAI/1.0',
            ])
            ->get($base.'/search', [
                'q' => $query,
                'format' => 'json',
                'categories' => 'general',
                'language' => $options['language'] ?? 'en',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'SearXNG request failed (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 400)
            );
        }

        $items = data_get($response->json(), 'results', []);
        $results = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (! is_array($item) || empty($item['url'])) {
                    continue;
                }
                $url = (string) $item['url'];
                if (! Str::startsWith($url, ['http://', 'https://'])) {
                    continue;
                }
                $results[] = [
                    'title' => (string) ($item['title'] ?? $url),
                    'url' => $url,
                    'snippet' => isset($item['content']) ? (string) $item['content'] : null,
                ];
            }
        }

        return $this->uniqueResults($results, $maxResults);
    }

    /**
     * @param  mixed  $json
     * @return list<array{title:string,url:string,snippet:?string}>
     */
    protected function parseLangsearch(mixed $json): array
    {
        $items = data_get($json, 'data.webPages.value');
        if (! is_array($items)) {
            $items = data_get($json, 'webPages.value');
        }
        if (! is_array($items)) {
            $items = data_get($json, 'data.organic');
        }
        if (! is_array($items)) {
            $items = data_get($json, 'results');
        }
        if (! is_array($items)) {
            return [];
        }

        $results = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = (string) ($item['url'] ?? $item['link'] ?? '');
            if ($url === '' || ! Str::startsWith($url, ['http://', 'https://'])) {
                continue;
            }
            $results[] = [
                'title' => (string) ($item['name'] ?? $item['title'] ?? $url),
                'url' => $url,
                'snippet' => isset($item['snippet'])
                    ? (string) $item['snippet']
                    : (isset($item['summary']) ? (string) $item['summary'] : null),
            ];
        }

        return $results;
    }

    /**
     * @param  list<array{title:string,url:string,snippet:?string}>  $results
     * @return list<array{title:string,url:string,snippet:?string}>
     */
    protected function uniqueResults(array $results, int $maxResults): array
    {
        $seen = [];
        $unique = [];
        foreach ($results as $result) {
            $key = Str::lower(rtrim($result['url'], '/'));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $result;
            if (count($unique) >= $maxResults) {
                break;
            }
        }

        return $unique;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    protected function includeDomains(array $options): array
    {
        $raw = $options['include_domains'] ?? $options['includeDomains'] ?? [];
        if (is_string($raw) && $raw !== '') {
            $raw = [$raw];
        }
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($domain) {
            $domain = strtolower(trim((string) $domain));
            $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
            $domain = preg_replace('#^www\.#', '', $domain) ?? $domain;

            return rtrim(explode('/', $domain)[0] ?? '', '.');
        }, $raw)));
    }

    protected function normalizeQuery(string $query): string
    {
        $query = preg_replace('/\bsite:\S+\s*/i', '', $query) ?? $query;

        return trim(preg_replace('/\s+/', ' ', $query) ?? $query);
    }

    protected function langsearchKey(): ?string
    {
        $settings = AISetting::current();
        $settings->applyProviderConfig();

        return $settings->resolvedLangsearchApiKey();
    }

    protected function searxngUrl(): ?string
    {
        return AISetting::current()->resolvedSearxngUrl();
    }
}
