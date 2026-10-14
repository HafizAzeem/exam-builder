<?php

namespace App\Services\PastPaperCollector\Providers;

use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Models\AISetting;
use App\Services\AIImport\GroqClient;
use Illuminate\Support\Str;

/**
 * Optional Groq browser_search. Production binds FreeWebSearchProvider instead —
 * Groq search 429s the free tier before question generate can run.
 */
class GroqWebSearchProvider implements WebSearchProvider
{
    public function __construct(
        protected GroqClient $groq,
    ) {}

    public function name(): string
    {
        return AISetting::PROVIDER_GROQ;
    }

    public function isConfigured(): bool
    {
        return AISetting::current()->isGroqUsable();
    }

    public function search(string $query, int $maxResults = 10, array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Groq is not configured. Set the Groq API key in AI Settings.');
        }

        $settings = AISetting::current();
        $settings->applyProviderConfig();
        $maxResults = max(1, min(10, $maxResults));
        $timeout = (int) ($options['timeout'] ?? $settings->search_timeout ?? 30);

        $model = $this->searchModel($settings);
        $prompt = $this->buildPrompt($query, $maxResults, $options);

        $extra = [];
        if ($this->supportsBrowserSearch($model)) {
            $extra['tools'] = [['type' => 'browser_search']];
            $extra['tool_choice'] = 'required';
        }

        $result = $this->groq->prompt($prompt, $model, $extra, max(20, $timeout));

        return array_slice($this->resultsFromText($result['content']), 0, $maxResults);
    }

    protected function searchModel(AISetting $settings): string
    {
        $model = $settings->resolvedGroqModel();
        if ($this->supportsBrowserSearch($model)) {
            return $model;
        }

        return 'openai/gpt-oss-20b';
    }

    protected function supportsBrowserSearch(string $model): bool
    {
        return in_array($model, [
            'openai/gpt-oss-20b',
            'openai/gpt-oss-120b',
            'openai/gpt-oss-safeguard-20b',
        ], true);
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

Use web/browser search to find real pages. Then return ONLY a JSON object:
{
  "results": [
    {"title": "...", "url": "https://...", "snippet": "..."}
  ]
}

Rules:
- Prefer direct PDF links for past papers when possible.
- Only include real http/https URLs you found.
- Do not invent URLs.
- Prefer BISE Lahore / Pakistani board sources.
PROMPT;
    }

    /**
     * @return list<array{title:string,url:string,snippet:?string}>
     */
    protected function resultsFromText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $json = null;
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $json = $decoded;
        } elseif (preg_match('/\{.*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        $results = [];
        if (is_array($json)) {
            $items = $json['results'] ?? $json;
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
                        'snippet' => isset($item['snippet']) ? (string) $item['snippet'] : null,
                    ];
                }
            }
        }

        if ($results === []) {
            preg_match_all('#https?://[^\s\"\'<>\]]+#i', $text, $matches);
            foreach ($matches[0] ?? [] as $url) {
                $url = rtrim($url, '.,);]');
                $results[] = [
                    'title' => $url,
                    'url' => $url,
                    'snippet' => null,
                ];
            }
        }

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
