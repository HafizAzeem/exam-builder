<?php

namespace App\Services\PastPaperCollector;

use Illuminate\Support\Str;

class UrlSafetyService
{
    /**
     * @var list<string>
     */
    protected array $blockedHosts = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        'metadata.google.internal',
    ];

    public function normalize(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$path.$query;
    }

    public function assertSafe(string $url): string
    {
        $normalized = $this->normalize($url);
        if (! $normalized) {
            throw new \InvalidArgumentException('Invalid URL.');
        }

        $parts = parse_url($normalized);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || in_array($host, $this->blockedHosts, true)) {
            throw new \InvalidArgumentException('Blocked host.');
        }

        if (Str::endsWith($host, '.local') || Str::endsWith($host, '.internal')) {
            throw new \InvalidArgumentException('Private host blocked.');
        }

        $ips = gethostbynamel($host) ?: [];
        if ($ips === []) {
            // Allow unresolved hosts to fail later at fetch time, but block literal IPs that are private.
            if (filter_var($host, FILTER_VALIDATE_IP) && $this->isPrivateIp($host)) {
                throw new \InvalidArgumentException('Private IP blocked.');
            }

            return $normalized;
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                throw new \InvalidArgumentException('Private IP blocked.');
            }
        }

        return $normalized;
    }

    public function isPrivateIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
