<?php

namespace App\Services\QuestionAssistant;

use App\Models\AIImport;
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
        $query = mb_substr(implode(' ', [$import->board, $import->grade->label_en, $import->subject->name_en, 'questions', $import->meta['curriculum_year'] ?? now()->year, mb_substr($import->meta['instructions'] ?? '', 0, 600), 'chapter '.$chapters->take(4)->map(fn ($c) => $c->number.' '.$c->title_en)->implode(' ')]), 0, 1500);
        $results = [];
        foreach (['oneYear', 'noLimit'] as $freshness) {
            $check();
            $remaining = (int) floor($deadline - microtime(true));
            if ($remaining < 10) {
                break;
            }
            $found = $this->search->search($query, 6, ['freshness' => $freshness, 'timeout' => min(15, $remaining), 'assistant' => true]);
            foreach ($found as $item) {
                if (isset($item['url'])) {
                    $results[$item['url']] = $item;
                }
            }
            if (count($results) >= 3) {
                break;
            }
        }
        $preferred = PreferredQuestionSite::where('is_active', true)->pluck('domain')->all();
        uasort($results, function ($a, $b) use ($preferred) {
            $score = fn ($r) => (preg_match('/\.(?:edu|gov)\.pk(?:\/|$)/i', $r['url']) ? 6 : 0) + (in_array(parse_url($r['url'], PHP_URL_HOST), $preferred, true) ? 3 : 0) + (str_starts_with((string) ($r['published_at'] ?? ''), (string) now()->year) ? 2 : 0);

            return $score($b) <=> $score($a);
        });
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
                    $text = trim($crawler->filter('body')->count() ? $crawler->filter('body')->text() : $crawler->text());
                } else {
                    continue;
                }
                if ($text === '' || mb_strlen($text) > 20000 - $total) {
                    continue;
                }
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
}
