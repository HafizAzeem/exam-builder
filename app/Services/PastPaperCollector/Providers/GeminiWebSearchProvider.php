<?php

namespace App\Services\PastPaperCollector\Providers;

use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Models\AISetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeminiWebSearchProvider implements WebSearchProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return filled(AISetting::current()->resolvedGeminiApiKey());
    }

    public function search(string $query, int $maxResults = 10, array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Gemini is not configured. Set the Gemini API key in AI Settings.');
        }

        $settings = AISetting::current();
        $settings->applyProviderConfig();

        $apiKey = $settings->resolvedGeminiApiKey();
        $model = $settings->resolvedTextModel() ?: 'gemini-3.5-flash-lite';
        $timeout = (int) ($options['timeout'] ?? $settings->search_timeout ?? 30);
        $maxResults = max(1, min(10, $maxResults));

        $prompt = $this->buildPrompt($query, $maxResults, $options);

        $baseUrl = rtrim((string) config('ai.providers.gemini.url', 'https://generativelanguage.googleapis.com/v1beta/'), '/');
        $endpoint = "{$baseUrl}/models/{$model}:generateContent";

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($endpoint.'?key='.urlencode((string) $apiKey), [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'tools' => [
                    ['google_search' => (object) []],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 2048,
                ],
            ]);

        if ($response->status() === 429) {
            throw new \RuntimeException('Gemini rate limit exceeded while searching for past papers.');
        }

        if (! $response->successful()) {
            // Fallback without google_search tool (some models/keys disallow grounding).
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($endpoint.'?key='.urlencode((string) $apiKey), [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 2048,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Gemini past-paper search failed: '.$response->body());
            }
        }

        $payload = $response->json() ?? [];
        $results = array_merge(
            $this->resultsFromGrounding($payload),
            $this->resultsFromText($payload),
        );

        return array_slice($this->dedupeByUrl($results), 0, $maxResults);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function buildPrompt(string $query, int $maxResults, array $options): string
    {
        $country = $options['country_code'] ?? 'pk';
        $language = $options['language'] ?? 'en';
        $fileType = $options['file_type'] ?? 'pdf';

        return <<<PROMPT
Find publicly available Pakistani board exam past papers matching this search.

Search query: {$query}
Preferred country: {$country}
Preferred language: {$language}
Preferred file type: {$fileType}
Maximum results: {$maxResults}

Return ONLY a JSON object with this shape:
{
  "results": [
    {"title": "...", "url": "https://...", "snippet": "..."}
  ]
}

Rules:
- Prefer direct PDF links for past papers when possible.
- Only include real http/https URLs.
- Do not invent URLs. If unsure, omit the result.
- Prefer BISE Lahore / Pakistani board sources.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{title:string,url:string,snippet:?string}>
     */
    protected function resultsFromGrounding(array $payload): array
    {
        $chunks = data_get($payload, 'candidates.0.groundingMetadata.groundingChunks', []);
        if (! is_array($chunks)) {
            return [];
        }

        $results = [];
        foreach ($chunks as $chunk) {
            $web = is_array($chunk) ? ($chunk['web'] ?? null) : null;
            if (! is_array($web) || empty($web['uri'])) {
                continue;
            }

            $url = (string) $web['uri'];
            if (! Str::startsWith($url, ['http://', 'https://'])) {
                continue;
            }

            $results[] = [
                'title' => (string) ($web['title'] ?? $url),
                'url' => $url,
                'snippet' => null,
            ];
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{title:string,url:string,snippet:?string}>
     */
    protected function resultsFromText(array $payload): array
    {
        $parts = data_get($payload, 'candidates.0.content.parts', []);
        if (! is_array($parts)) {
            return [];
        }

        $text = '';
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $json = $this->extractJson($text);
        if (! is_array($json)) {
            return $this->urlsFromPlainText($text);
        }

        $items = $json['results'] ?? $json;
        if (! is_array($items)) {
            return [];
        }

        $results = [];
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
                'snippet' => isset($item['snippet']) ? (string) $item['snippet'] : null,
            ];
        }

        return $results;
    }

    /**
     * @return list<array{title:string,url:string,snippet:?string}>
     */
    protected function urlsFromPlainText(string $text): array
    {
        preg_match_all('#https?://[^\s\"\'<>\]]+#i', $text, $matches);
        $results = [];

        foreach ($matches[0] ?? [] as $url) {
            $url = rtrim($url, '.,);]');
            $results[] = [
                'title' => $url,
                'url' => $url,
                'snippet' => null,
            ];
        }

        return $results;
    }

    protected function extractJson(string $text): ?array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
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
