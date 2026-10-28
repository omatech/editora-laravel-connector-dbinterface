<?php

namespace Omatech\Editora\Connector\Cache;

use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Omatech\Editora\Connector\DoctrineConnection;
use Omatech\Editora\Extractor\Extractor;
use ReflectionFunction;

/**
 * Caches complete public content graphs, including their pagination state, for the extractor methods
 * listed in $methods. An entry is fresh for $ttl seconds, never past the last publication, and up to
 * $staleTtl is served at once and refreshed after the response; serving it marks the request as stale for
 * the page cache. Nested calls, preview, debug and avoid_cache always read the database.
 */
class CachedExtractor extends Extractor
{
    public const METHODS = [
        'findInstanceById', 'findInstancesInClass', 'findInstancesInList',
        'findInstanceByIdAllElements', 'findChildrenInstances', 'findParentInstances',
    ];

    private int $depth = 0;

    public function __construct($connection, private Repository $cache, private int $ttl = 60, private string $namespace = 'v1', $params = [], private int $staleTtl = 300, private array $methods = self::METHODS, private ?ContentFreshness $freshness = null)
    {
        parent::__construct($connection, $params);
    }

    /** Makes the connector's 'Extractor' binding this class, configured by editora-cache. */
    public static function register(Application $app, array $methods = self::METHODS): void
    {
        $app->bind('Extractor', fn ($app) => new self(
            DoctrineConnection::fromLaravel(DB::connection()->getConfig()),
            $app['cache']->store(config('editora-cache.content.store')),
            config('editora-cache.content.enabled') ? (int) config('editora-cache.content.ttl') : 0,
            $app->make(CacheVersion::class)->current(),
            [],
            (int) config('editora-cache.content.stale_ttl'),
            $methods,
            $app->make(ContentFreshness::class),
        ));
    }

    public function findInstanceById($inst_id, $params = null, ?callable $callback = null)
    {
        return $this->cached(__FUNCTION__, [$inst_id, $params], $callback,
            fn () => parent::findInstanceById($inst_id, $params, $callback));
    }

    public function findInstancesInClass($class, $num = null, $params = null, ?callable $callback = null)
    {
        return $this->cached(__FUNCTION__, [$class, $num, $params], $callback,
            fn () => parent::findInstancesInClass($class, $num, $params, $callback));
    }

    public function findInstancesInList($inst_ids, $num = null, $class = null, $params = null, ?callable $callback = null)
    {
        return $this->cached(__FUNCTION__, [$inst_ids, $num, $class, $params], $callback,
            fn () => parent::findInstancesInList($inst_ids, $num, $class, $params, $callback));
    }

    public function findInstanceByIdAllElements($inst_id, $params = null, $num = null, $level = 1, ?callable $callback = null)
    {
        return $this->cached(__FUNCTION__, [$inst_id, $params, $num, $level], $callback,
            fn () => parent::findInstanceByIdAllElements($inst_id, $params, $num, $level, $callback));
    }

    public function findChildrenInstances($inst_id, $relation, $num = null, $params = null, ?callable $callback = null)
    {
        return $this->cached(__FUNCTION__, [$inst_id, $relation, $num, $params], $callback,
            fn () => parent::findChildrenInstances($inst_id, $relation, $num, $params, $callback));
    }

    public function findParentInstances($inst_id, $relation, $num = null, $params = null, ?callable $callback = null)
    {
        return $this->cached(__FUNCTION__, [$inst_id, $relation, $num, $params], $callback,
            fn () => parent::findParentInstances($inst_id, $relation, $num, $params, $callback));
    }

    private function cached(string $method, array $arguments, ?callable $callback, Closure $load)
    {
        // Nested calls belong to the outer graph. Never share preview/debug results.
        if (!in_array($method, $this->methods, true) || $this->depth > 0 || $this->preview || $this->debug || $this->avoid_cache || $this->ttl <= 0) {
            return $load();
        }

        $identity = null;
        if ($callback !== null) {
            if (!$callback instanceof Closure) {
                return $load();
            }
            $reflection = new ReflectionFunction($callback);
            $captures = $reflection->getStaticVariables();
            foreach ($captures as &$value) {
                if ($value === $this) {
                    $value = self::class;
                }
            }
            unset($value);
            // Unknown captured objects may contain request/user state: bypass caching.
            if ($reflection->getClosureThis() !== null || $this->containsObject($captures)) {
                return $load();
            }
            $file = $reflection->getFileName();
            $identity = [$file, $file ? hash_file('sha256', $file) : null,
                $reflection->getStartLine(), $reflection->getEndLine(), $captures];
        }
        if ($this->containsObject($arguments)) {
            return $load();
        }

        $key = 'cms:'.$this->namespace.':graph:'.hash('sha256', serialize([
            array_intersect_key($this->conn->getParams(), array_flip(['driver', 'host', 'port', 'dbname', 'path', 'memory'])),
            $this->lang, $this->metadata, $this->timings, $this->extract_values,
            $this->default_language_to_remove_from_url, $this->default_limit,
            $this->paginator, $method, $arguments, $identity,
        ]));
        // A deferred refresh must use this call's state, even if the extractor is reused.
        $context = array_intersect_key(get_object_vars($this), array_flip([
            'lang', 'metadata', 'timings', 'extract_values', 'default_limit', 'params',
            'default_language_to_remove_from_url', 'preview', 'preview_date', 'debug',
            'avoid_cache', 'paginator', 'last_updated_timestamp',
        ]));
        $fresh = $this->freshness?->freshSeconds($this->ttl) ?? $this->ttl;
        $entry = $this->cache->flexible($key, [$fresh, max($this->ttl, $this->staleTtl)], function () use ($load, $context) {
            $restore = array_intersect_key(get_object_vars($this), $context);
            foreach ($context as $property => $value) {
                $this->$property = $value;
            }
            $this->depth++;
            try {
                $read = Carbon::now()->getTimestamp();
                $result = $load();
                return ['result' => $result, 'paginator' => $this->paginator, 'updated' => $this->last_updated_timestamp, 'read' => $read];
            } finally {
                $this->depth--;
                foreach ($restore as $property => $value) {
                    $this->$property = $value;
                }
            }
        }, ['seconds' => 30]);
        // Judged by when the database was read, so a read that a publication overtook is not fresh either.
        if ($this->freshness?->fresh($entry['read'] ?? 0, $this->ttl) === false) {
            $this->freshness->servedStale();
        }
        $this->paginator = $entry['paginator'];
        $this->last_updated_timestamp = max($this->last_updated_timestamp, $entry['updated']);

        return $entry['result'];
    }

    private function containsObject(array $values): bool
    {
        foreach ($values as $value) {
            if (is_object($value) || is_resource($value) || (is_array($value) && $this->containsObject($value))) {
                return true;
            }
        }
        return false;
    }
}
