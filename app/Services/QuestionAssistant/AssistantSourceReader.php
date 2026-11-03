<?php

namespace App\Services\QuestionAssistant;

use App\Models\AIImport;
use App\Models\Board;
use App\Models\Chapter;
use App\Models\PreferredQuestionSite;
use App\Services\PastPaperCollector\Providers\FreeWebSearchProvider;
use App\Services\PastPaperCollector\UrlSafetyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser;
use Symfony\Component\DomCrawler\Crawler;

class AssistantSourceReader
{
    public function __construct(private FreeWebSearchProvider $search, private UrlSafetyService $safety) {}

    public function sources(AIImport $import, float $deadline, callable $check): array
    {
        if (! $this->search->isConfigured()) {
            throw new \RuntimeException('Online question search is not configured. Use Format my questions or ask the platform administrator to enable search.');
        }
        $chapters = Chapter::whereIn('id', $import->chapterIds())->orderBy('number')->get();
        // A freshness request is not a request for syllabus notices. Keep retrieval
        // focused on question text, without requiring four unrelated chapter names.
        $instructions = preg_replace('/\b(?:(?:latest|current|updated|new)\s+)?(?:syllabus|salybas|slaybas)\b/iu', '', $import->meta['instructions'] ?? '');
        $preferred = PreferredQuestionSite::where('is_active', true)->orderBy('priority')->pluck('domain')->all();
        $region = Board::where('name', $import->board)->value('region');
        $results = [];
        foreach (['oneYear', 'noLimit'] as $attempt => $freshness) {
            $check();
            $remaining = (int) floor($deadline - microtime(true));
            if ($remaining < 10) {
                break;
            }
            // One focused chapter per query avoids returning whole-book directories.
            // Country is explicit: "Punjab Board" alone also matches Indian boards.
            $chapterTerms = $chapters->get(min($attempt, max(0, $chapters->count() - 1)))?->title_en ?? '';
            $query = trim(preg_replace('/\s+/u', ' ', implode(' ', [$import->grade->label_en, $import->subject->name_en, $chapterTerms, 'exercise questions', $region ?: $import->board, 'Pakistan', mb_substr($instructions, 0, 300)])));
            $options = ['freshness' => $freshness, 'timeout' => min(15, $remaining), 'assistant' => true];
            if ($attempt === 0 && $preferred) {
                $options['include_domains'] = array_slice($preferred, 0, 3);
            }
            $found = $this->search->search($query, 6, $options);
            foreach ($found as $item) {
                if (isset($item['url']) && $this->relevant($item, $import, $region)) {
                    $results[$item['url']] = $item;
                }
            }
            if (count(array_filter($results, fn ($item) => $this->score($item, $preferred, $import) >= 15)) >= 3) {
                break;
            }
        }
        // Some question pages are readable but absent from the search index.
        // Use only inspected URLs matching the selected curriculum and active sites.
        $knownUrls = [];
        foreach (config('question-assistant.question_pages', []) as $page) {
            if (! in_array($page['domain'], $preferred, true) || $page['region'] !== $region || (int) $page['grade'] !== (int) $import->grade->number || strcasecmp($page['subject'], $import->subject->name_en) !== 0 || ! $chapters->contains(fn ($chapter) => (int) $chapter->number === (int) $page['chapter'] && strcasecmp($chapter->title_en, $page['chapter_title']) === 0)) {
                continue;
            }
            $knownUrls[] = $page['url'];
            $results[$page['url']] = ['url' => $page['url'], 'title' => $page['subject'].' '.$page['chapter_title'].' exercise questions'];
        }
        uasort($results, fn ($a, $b) => ($this->score($b, $preferred, $import) + (in_array($b['url'], $knownUrls, true) ? 50 : 0)) <=> ($this->score($a, $preferred, $import) + (in_array($a['url'], $knownUrls, true) ? 50 : 0)));
        $sources = [];
        $total = 0;
        $attempted = 0;
        foreach ($results as $result) {
            if ($attempted >= 3 || $total >= 20000 || microtime(true) > $deadline - 15) {
                break;
            }
            $check();
            $attempted++;
            try {
                $url = $this->safety->assertSafe($result['url']);
                $response = Http::timeout(max(1, min(12, (int) ($deadline - microtime(true) - 10))))->connectTimeout(5)->withOptions([
                    'allow_redirects' => false,
                    'on_headers' => function ($response) {
                        if ((int) $response->getHeaderLine('Content-Length') > 1024 * 1024) {
                            throw new \RuntimeException('Source is too large.');
                        }
                    },
                    'progress' => function ($downloadTotal, $downloaded) {
                        if ($downloadTotal > 1024 * 1024 || $downloaded > 1024 * 1024) {
                            throw new \RuntimeException('Source is too large.');
                        }
                    },
                ])->withHeaders(['User-Agent' => 'ExamBuilderQuestionAssistant/1.0', 'Accept' => 'text/html,text/plain,application/pdf'])->get($url);
                if (! $response->successful() || strlen($response->body()) > 1024 * 1024) {
                    continue;
                }
                $mime = strtolower(explode(';', $response->header('Content-Type') ?: '')[0]);
                $date = $result['published_at'] ?? null;
                if ($mime === 'application/pdf') {
                    $pdf = (new Parser)->parseContent($response->body());
                    if (count($pdf->getPages()) > 5) {
                        continue;
                    }
                    $text = trim($pdf->getText());
                } elseif ($mime === 'text/plain') {
                    $text = trim($response->body());
                } elseif ($mime === 'text/html' || $mime === 'application/xhtml+xml') {
                    $crawler = new Crawler($response->body());
                    foreach (['meta[property="article:published_time"]', 'meta[property="article:modified_time"]', 'meta[name="date"]'] as $selector) {
                        if (! $date && $crawler->filter($selector)->count()) {
                            $date = $crawler->filter($selector)->first()->attr('content');
                        }
                    }
                    $crawler->filter('script,style,nav,footer,header,noscript,form')->each(fn ($node) => $node->getNode(0)?->parentNode?->removeChild($node->getNode(0)));
                    $content = $crawler->filter('main,article')->count() ? $crawler->filter('main,article')->first() : ($crawler->filter('body')->count() ? $crawler->filter('body') : $crawler);
                    $text = trim($content->text());
                    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                    if (($host === 'prepsure.pk' || $host === 'www.prepsure.pk') && $content->filter('span.sr-only[aria-hidden="true"]')->count()) {
                        // This source supplies an explicit plain-text copy of every
                        // question beside rendered math. Use those copies so roots,
                        // fractions and operators survive, without worked solutions.
                        $text = implode("\n\n", $content->filter('span.sr-only[aria-hidden="true"]')->each(fn ($node) => trim($node->text())));
                    }
                } else {
                    continue;
                }
                if ($text === '') {
                    continue;
                }
                // A long exercise page should contribute bounded source text, not
                // be discarded wholesale because it includes worked solutions.
                $text = mb_substr($text, 0, min(10000, 20000 - $total));
                try {
                    $date = $date ? Carbon::parse($date)->toIso8601String() : null;
                    if ($date && Carbon::parse($date)->isFuture()) {
                        $date = null;
                    }
                } catch (\Throwable) {
                    $date = null;
                }
                $sources[] = ['text' => $text, 'url' => $url, 'published_at' => $date];
                $total += mb_strlen($text);
            } catch (\Throwable $e) {
                if (microtime(true) > $deadline) {
                    throw $e;
                }
            }
        }
        if (! $sources) {
            throw new \RuntimeException('No readable question sources matched this request. Try a more specific chapter or paste your own questions.');
        }

        return $sources;
    }

    private function score(array $result, array $preferred, AIImport $import): int
    {
        $title = $result['title'] ?? '';
        $host = strtolower((string) parse_url($result['url'], PHP_URL_HOST));
        $preferredHost = collect($preferred)->contains(fn ($domain) => $host === $domain || str_ends_with($host, '.'.$domain));
        $score = ($preferredHost ? 3 : 0)
            + (preg_match('/\.(?:edu|gov)\.pk$/i', $host) ? 4 : 0)
            + (str_starts_with((string) ($result['published_at'] ?? ''), (string) now()->year) ? 2 : 0);
        // Resource directories often mention questions in their snippets but hold
        // no actual questions. Rank by the page title before spending fetch/AI calls.
        if (preg_match('/exercise|mcqs?|short questions?|question paper|model paper|sample paper|practice (?:test|questions?)|review questions?/iu', $title)) {
            $score += 15;
        }
        if (preg_match('/syllabus|curriculum|paper (?:scheme|pattern)|video|lectures?|archive|past papers|book introduction|complete guide|roll number|date sheet|results?\b|admissions?/iu', $title)) {
            $score -= 12;
        }
        if (! str_contains(strtolower($import->subject->name_en), 'general') && preg_match('/general (?:math|mathematics)/iu', $title)) {
            $score -= 20;
        }

        return $score;
    }

    private function relevant(array $result, AIImport $import, ?string $region): bool
    {
        $text = ($result['title'] ?? '').' '.mb_substr($result['snippet'] ?? '', 0, 500);
        if (preg_match('/\b(?:CBSE|ICSE|NCERT|Samacheer|PSEB|PBSE|MPBSE|Madhya Pradesh|Maharashtra|India)\b/iu', $text)) {
            return false;
        }
        if (strtolower($region ?? '') === 'punjab' && preg_match('/\b(?:KPK|Khyber|Sindh|Karachi|FBISE|Federal|Balochistan)\b/iu', $text.' '.$result['url'])) {
            return false;
        }
        if (preg_match('/roll number|date sheet|admissions?|exam results?/iu', $result['title'] ?? '')) {
            return false;
        }
        if (! str_contains(strtolower($import->subject->name_en), 'general') && preg_match('/general (?:math|mathematics)/iu', $result['title'] ?? '')) {
            return false;
        }

        return true;
    }
}
