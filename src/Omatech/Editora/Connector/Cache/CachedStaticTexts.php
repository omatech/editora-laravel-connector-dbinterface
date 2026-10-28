<?php

namespace Omatech\Editora\Connector\Cache;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Omatech\Editora\Connector\Models\StaticTexts;

/** Loads Editora static texts once per language and keeps them with the content cache's freshness, publications included. */
class CachedStaticTexts
{
    private array $languages = [];

    public function __construct(private Repository $cache, private int $ttl, private string $namespace, private int $staleTtl = 300, private ?ContentFreshness $freshness = null)
    {
    }

    /** One instance per request, configured by editora-cache; the application's _statictext() helper uses it. */
    public static function register(Application $app): void
    {
        $app->scoped(self::class, fn ($app) => new self(
            $app['cache']->store(config('editora-cache.content.store')),
            config('editora-cache.content.enabled') ? (int) config('editora-cache.content.ttl') : 0,
            $app->make(CacheVersion::class)->current(),
            (int) config('editora-cache.content.stale_ttl'),
            $app->make(ContentFreshness::class),
        ));
    }

    public function all(string $locale): array
    {
        if ($this->ttl <= 0 || request()->attributes->get('preview') || request()->has('preview')) {
            return StaticTexts::todos_lang($locale);
        }
        return $this->languages[$locale] ??= $this->load($locale);
    }

    private function load(string $locale): array
    {
        $key = 'cms:'.$this->namespace.':texts:v2:'.hash('sha256', config('database.default').':'.config('database.connections.'.config('database.default').'.database').':'.$locale);
        $ttl = [$this->freshness?->freshSeconds($this->ttl) ?? $this->ttl, max($this->ttl, $this->staleTtl)];
        $read = fn () => ['read' => Carbon::now()->getTimestamp(), 'texts' => StaticTexts::todos_lang($locale)];
        $entry = $this->cache->flexible($key, $ttl, $read, ['seconds' => 30]);
        if ($this->freshness?->mustReload($entry['read'])) {
            // Without a value flexible() reads at once; the refresh it deferred finds a newer copy and stands down.
            $this->cache->forget($key);
            $entry = $this->cache->flexible($key, $ttl, $read, ['seconds' => 30]);
        }
        if ($this->freshness?->fresh($entry['read'], $this->ttl) === false) {
            $this->freshness->servedStale();
        }

        return $entry['texts'];
    }

    public function get($text, array $replacements = [], ?string $locale = null): string
    {
        if (!is_string($text)) {
            throw new \InvalidArgumentException('You must supply a string to be translated.');
        }
        $locale = $locale ?: app()->getLocale();
        $texts = $this->all($locale);
        // Preserve Editora's first-or-create behavior for missing translations.
        $value = $texts[$text] ?? (new StaticTexts)->getOneStaticText($text, [], $locale);
        if (!array_key_exists($text, $texts) && $this->ttl > 0) {
            $this->languages[$locale][$text] = $value;
        }
        foreach ($replacements as $key => $replacement) {
            $value = str_replace('#'.$key.'#', $replacement, $value);
        }
        return $value;
    }
}
