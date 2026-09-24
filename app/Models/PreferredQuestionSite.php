<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PreferredQuestionSite extends Model
{
    public const SOURCE_EXERCISE = 'exercise';

    public const SOURCE_PAST_PAPER = 'past_paper';

    public const SOURCE_ONLINE_PRACTICE = 'online_practice';

    public const SOURCE_TYPES = [
        self::SOURCE_EXERCISE,
        self::SOURCE_PAST_PAPER,
        self::SOURCE_ONLINE_PRACTICE,
    ];

    protected $fillable = [
        'name',
        'domain',
        'source_types',
        'priority',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'source_types' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#^www\.#', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0] ?? $domain;

        return rtrim($domain, '/.');
    }

    public function setDomainAttribute(string $value): void
    {
        $this->attributes['domain'] = self::normalizeDomain($value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForContentSource(Builder $query, ?string $contentSource): Builder
    {
        $source = match ($contentSource) {
            'past_paper' => self::SOURCE_PAST_PAPER,
            'online_practice', 'additional' => self::SOURCE_ONLINE_PRACTICE,
            default => self::SOURCE_EXERCISE,
        };

        return $query->where(function (Builder $q) use ($source) {
            $q->whereNull('source_types')
                ->orWhereJsonLength('source_types', 0)
                ->orWhereJsonContains('source_types', $source);
        });
    }

    /**
     * @return list<string>
     */
    public static function activeDomainsFor(?string $contentSource): array
    {
        return static::query()
            ->active()
            ->forContentSource($contentSource)
            ->orderBy('priority')
            ->orderBy('id')
            ->pluck('domain')
            ->map(fn ($d) => self::normalizeDomain((string) $d))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ordered active site rows for waterfall harvest.
     *
     * @return list<static>
     */
    public static function orderedActiveFor(?string $contentSource): array
    {
        return static::query()
            ->active()
            ->forContentSource($contentSource)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function label(): string
    {
        return $this->name ?: Str::title($this->domain);
    }
}
