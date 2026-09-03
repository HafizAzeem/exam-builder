<?php

namespace App\Services\PastPaperCollector\Providers;

use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Models\AISetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleProgrammableSearchProvider implements WebSearchProvider
{
    public function name(): string
    {
        return 'google_cse';
    }

    public function isConfigured(): bool
    {
        $settings = AISetting::current();

        return filled($settings->resolvedGoogleSearchApiKey())
            && filled($settings->resolvedGoogleCseId());
    }

    public function search(string $query, int $maxResults = 10, array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Google Programmable Search is not configured. Set API key and CSE ID in AI Settings.');
        }

        $settings = AISetting::current();
        $apiKey = $settings->resolvedGoogleSearchApiKey();
        $cx = $settings->resolvedGoogleCseId();
        $timeout = (int) ($options['timeout'] ?? $settings->search_timeout ?? 30);
        $maxResults = max(1, min(10, $maxResults));

        $params = [
            'key' => $apiKey,
            'cx' => $cx,
            'q' => $query,
            'num' => $maxResults,
            'safe' => 'active',
            'gl' => $options['country_code'] ?? 'pk',
            'hl' => $options['language'] ?? 'en',
        ];

        if (! empty($options['file_type'])) {
            $params['fileType'] = $options['file_type'];
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get('https://www.googleapis.com/customsearch/v1', $params);

        if ($response->status() === 429) {
            throw new \RuntimeException('Google Programmable Search rate limit exceeded.');
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Google Programmable Search failed: '.$response->body());
        }

        $items = $response->json('items') ?? [];
        $results = [];

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['link'])) {
                continue;
            }

            $results[] = [
                'title' => (string) ($item['title'] ?? ''),
                'url' => (string) $item['link'],
                'snippet' => isset($item['snippet']) ? (string) $item['snippet'] : null,
            ];
        }

        return $this->dedupeByUrl($results);
    }

    /**
     * @param  list<array{title:string,url:string,snippet:?string}>  $results
     * @return list<array{title:string,url:string,snippet:?string}>
     */
    protected function dedupeByUrl(array $results): array
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
        }

        return $unique;
    }
}
