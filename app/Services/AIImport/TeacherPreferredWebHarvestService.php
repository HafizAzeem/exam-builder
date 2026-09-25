<?php

namespace App\Services\AIImport;

use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Models\AIImport;
use App\Models\Chapter;
use App\Models\PreferredQuestionSite;
use App\Services\PastPaperCollector\UrlSafetyService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Harvest ready-made practice questions from preferred education websites first.
 *
 * Strategy: waterfall per site (stop early when enough questions) to cut rate limits / tokens.
 * If no active preferred sites exist, returns [] so the job falls back to LLM invent.
 */
class TeacherPreferredWebHarvestService
{
    public function __construct(
        protected WebSearchProvider $search,
        protected UrlSafetyService $urlSafety,
        protected GeminiService $gemini,
        protected DocumentChunkerService $chunker,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function harvest(AIImport $import): array
    {
        if (! $this->search->isConfigured()) {
            return [];
        }

        $contentSources = $import->meta['content_sources'] ?? null;
        if (! is_array($contentSources) || $contentSources === []) {
            $contentSources = [
                $import->meta['content_source']
                    ?? match ($import->book_type) {
                        'past_paper' => 'past_paper',
                        'additional_questions' => 'online_practice',
                        default => 'exercise',
                    },
            ];
        }

        $sites = [];
        $seenDomains = [];
        foreach ($contentSources as $contentSource) {
            foreach (PreferredQuestionSite::orderedActiveFor((string) $contentSource) as $site) {
                $domain = PreferredQuestionSite::normalizeDomain((string) $site->domain);
                if ($domain === '' || isset($seenDomains[$domain])) {
                    continue;
                }
                $seenDomains[$domain] = true;
                $sites[] = $site;
            }
        }

        // Fallback to env/config list if DB empty (fresh install before seeder).
        if ($sites === []) {
            $domains = config('exam.preferred_question_sites', []);
            if ($domains === []) {
                return [];
            }
            $sites = collect($domains)->map(function ($domain, $i) {
                $site = new PreferredQuestionSite([
                    'name' => $domain,
                    'domain' => PreferredQuestionSite::normalizeDomain((string) $domain),
                    'priority' => ($i + 1) * 10,
                    'is_active' => true,
                    'source_types' => PreferredQuestionSite::SOURCE_TYPES,
                ]);

                return $site;
            })->all();
        }

        $import->loadMissing(['grade', 'subject']);
        $chapters = Chapter::query()
            ->where('subject_id', $import->subject_id)
            ->whereIn('id', $import->chapterIds() ?: [0])
            ->orderBy('number')
            ->get(['id', 'number', 'title_en']);

        $needed = $this->neededCount($import);
        $maxUrlsPerSite = max(1, min(3, (int) config('exam.preferred_sites_max_urls_per_site', 2)));
        $maxSites = max(1, min(8, (int) config('exam.preferred_sites_max_sites', 4)));

        $questions = [];
        $chunkIndex = 100;
        $seenUrls = [];

        foreach (array_slice($sites, 0, $maxSites) as $site) {
            if (count($questions) >= $needed) {
                break;
            }

            $domain = PreferredQuestionSite::normalizeDomain((string) $site->domain);
            if ($domain === '') {
                continue;
            }

            $urls = $this->searchSiteUrls($import, $chapters, $domain, $maxUrlsPerSite, $seenUrls);
            foreach ($urls as $url) {
                $seenUrls[$url] = true;
                try {
                    $text = $this->fetchPageText($url);
                    if (mb_strlen($text) < 120) {
                        continue;
                    }

                    $chunks = $this->chunker->chunk($text);
                    foreach (array_slice($chunks, 0, 2) as $chunk) {
                        $result = $this->gemini->extractQuestions(
                            $import,
                            $chunk['text'],
                            $chunkIndex++,
                            ($chunk['title'] ?? null) ?: parse_url($url, PHP_URL_HOST)
                        );
                        foreach ($result['questions'] as $q) {
                            if (is_array($q)) {
                                $q['source_url'] = $url;
                                $questions[] = $q;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    Log::warning('Preferred site harvest failed for '.$url.': '.$e->getMessage());
                }

                if (count($questions) >= $needed) {
                    break;
                }
            }
        }

        return $questions;
    }

    protected function neededCount(AIImport $import): int
    {
        $counts = $import->meta['counts'] ?? [];
        $sum = array_sum(array_map('intval', is_array($counts) ? $counts : []));

        return max(1, $sum);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Chapter>  $chapters
     * @param  array<string, bool>  $seenUrls
     * @return list<string>
     */
    protected function searchSiteUrls(AIImport $import, $chapters, string $domain, int $maxUrls, array $seenUrls): array
    {
        $queries = $this->buildQueriesForSite($import, $chapters, $domain);
        $urls = [];

        foreach ($queries as $query) {
            try {
                $results = $this->search->search($query, min(5, $maxUrls + 2), [
                    'timeout' => 25,
                ]);
            } catch (Throwable $e) {
                Log::warning('Preferred site search failed: '.$e->getMessage());

                continue;
            }

            foreach ($results as $row) {
                $url = $row['url'] ?? null;
                if (! is_string($url) || $url === '' || isset($seenUrls[$url]) || isset($urls[$url])) {
                    continue;
                }
                if (! $this->urlAllowed($url, [$domain])) {
                    continue;
                }
                $urls[$url] = true;
                if (count($urls) >= $maxUrls) {
                    break 2;
                }
            }
        }

        return array_keys($urls);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Chapter>  $chapters
     * @return list<string>
     */
    protected function buildQueriesForSite(AIImport $import, $chapters, string $site): array
    {
        $grade = $import->grade?->label_en ?? ('Class '.$import->grade_id);
        $subject = $import->subject?->name_en ?? 'Subject';
        $board = $import->board ?: 'Lahore Board';
        $chapterTitle = $chapters->first()?->title_en;
        $langHint = strtolower((string) $import->language) === 'urdu' ? 'Urdu' : 'MCQ';

        $contentHint = match ($import->book_type) {
            'past_paper' => 'past paper',
            'additional_questions' => 'online test MCQ notes',
            default => 'exercise',
        };

        $site = preg_replace('#^www\.#i', '', strtolower(trim($site))) ?: $site;
        $queries = [];

        $base = trim("site:{$site} {$grade} {$subject} {$chapterTitle} {$contentHint} {$board} {$langHint}");
        $queries[] = preg_replace('/\s+/', ' ', $base) ?? $base;

        if ($chapterTitle) {
            $queries[] = "site:{$site} {$grade} {$subject} {$contentHint} {$langHint}";
        }

        return array_values(array_unique($queries));
    }

    /**
     * @param  list<string>  $sites
     */
    protected function urlAllowed(string $url, array $sites): bool
    {
        try {
            $normalized = $this->urlSafety->assertSafe($url);
        } catch (Throwable) {
            return false;
        }

        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));
        $host = preg_replace('#^www\.#', '', $host) ?? $host;

        foreach ($sites as $site) {
            $site = PreferredQuestionSite::normalizeDomain((string) $site);
            if ($site !== '' && ($host === $site || Str::endsWith($host, '.'.$site))) {
                return true;
            }
        }

        return false;
    }

    protected function fetchPageText(string $url): string
    {
        $safe = $this->urlSafety->assertSafe($url);
        $response = Http::timeout(25)
            ->withHeaders([
                'User-Agent' => 'ExamBuilderTeacherAI/1.0',
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            ])
            ->get($safe);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status());
        }

        $html = $response->body();
        if (strlen($html) > 2_000_000) {
            $html = substr($html, 0, 2_000_000);
        }

        try {
            $crawler = new Crawler($html);
            $crawler->filter('script, style, noscript, nav, footer, header, iframe')->each(function (Crawler $node) {
                foreach ($node as $dom) {
                    $dom->parentNode?->removeChild($dom);
                }
            });
            $text = $crawler->text('');
        } catch (Throwable) {
            $text = strip_tags($html);
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
