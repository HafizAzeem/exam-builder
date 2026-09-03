<?php

namespace App\Contracts\PastPaperCollector;

interface WebSearchProvider
{
    /**
     * @return list<array{title:string,url:string,snippet:?string}>
     */
    public function search(string $query, int $maxResults = 10, array $options = []): array;

    public function name(): string;

    public function isConfigured(): bool;
}
