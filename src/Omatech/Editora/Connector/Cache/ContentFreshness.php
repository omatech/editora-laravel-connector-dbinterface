<?php

namespace Omatech\Editora\Connector\Cache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use LogicException;
use Throwable;

/**
 * Whether cached CMS content is still current. A publication is recorded, as a time, whenever the Editora
 * admin writes public content; content cached before it stops being fresh, so it is served once more and
 * refreshed after the response, and no visitor waits for a cold render. A page rendered from content that
 * was not fresh must not be stored. Unlike a CacheVersion change, a publication discards nothing.
 *
 * A refresh that read the database before a publication and stored after it keeps serving that content
 * for at most one TTL, and no page rendered from it is stored meanwhile.
 */
final class ContentFreshness
{
    private const KEY = 'editora-cache:published-at';
    private const ADMIN = 'Omatech\Editora\Admin\Controller';
    /** Admin tables that no public page reads: sign-in, favourites, recent items, roles and permissions. */
    private const PRIVATE_TABLES = ['omp_users', 'omp_user_instances', 'omp_roles', 'omp_roles_classes', 'omp_instances_roles'];
    private const WRITE = '/^\s*(?:INSERT|REPLACE|UPDATE|DELETE|TRUNCATE|ALTER|DROP|CREATE|RENAME)\b/i';
    private const TARGET = '/^\s*(?:(?:INSERT|REPLACE)(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE))*(?:\s+INTO)?'
        .'|UPDATE(?:\s+(?:LOW_PRIORITY|IGNORE))*|DELETE(?:\s+(?:LOW_PRIORITY|QUICK|IGNORE))*\s+FROM)\s+`?(\w+)`?[\s(]/i';

    /** A null store means publications are not tracked: content is fresh for its whole TTL. */
    public function __construct(private ?Repository $store = null)
    {
    }

    /**
     * Records a publication after every public write made by an Editora admin request. All admin writes go
     * through the Laravel connection, so saves, publication, deletion, cloning, relations, ordering, static
     * texts, images and imports count alike; visitor requests and console commands never do.
     */
    public static function register(Application $app): void
    {
        $app->singleton(self::class, fn ($app) => new self($app['cache']->store(config('editora-cache.content.store'))));
        $app['events']->listen(QueryExecuted::class, function (QueryExecuted $query) use ($app): void {
            if ($app['request']->route()?->getControllerClass() === self::ADMIN && self::changesPublicContent($query->sql)) {
                $app->make(self::class)->published();
            }
        });
    }

    public static function changesPublicContent(string $sql): bool
    {
        if (!preg_match(self::WRITE, $sql)) {
            return false;
        }

        return !preg_match(self::TARGET, $sql, $match) || !in_array(strtolower($match[1]), self::PRIVATE_TABLES, true);
    }

    /** Time of the last publication, read once per request; 0 when publications are not tracked. */
    public function lastPublication(): int
    {
        $request = request();
        if (!$request->attributes->has(self::KEY)) {
            $request->attributes->set(self::KEY, $this->store === null ? 0 : (int) $this->store->get(self::KEY, 0));
        }

        return $request->attributes->get(self::KEY);
    }

    /**
     * Records a publication now, at most once per second and request. A failure is reported, not thrown: the
     * admin's write is already committed and the rest of its statements must still run.
     */
    public function published(): void
    {
        if ($this->store === null) {
            throw new LogicException('Publications are not tracked.');
        }
        $now = Carbon::now()->getTimestamp();
        if (request()->attributes->get(self::KEY) === $now) {
            return;
        }
        try {
            $this->store->forever(self::KEY, $now);
            request()->attributes->set(self::KEY, $now);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Freshness window for content cached for $ttl seconds: nothing cached up to the last publication is fresh. */
    public function freshSeconds(int $ttl): int
    {
        $published = $this->lastPublication();

        return $published === 0 ? $ttl : max(0, min($ttl, Carbon::now()->getTimestamp() - $published));
    }

    /** Whether content read from the database at $readAt is still fresh for a $ttl-second cache. */
    public function fresh(int $readAt, int $ttl): bool
    {
        return $readAt > $this->lastPublication() && $readAt + $ttl > Carbon::now()->getTimestamp();
    }

    public function servedStale(): void
    {
        request()->attributes->set(self::class.':stale', true);
    }

    public function stale(): bool
    {
        return request()->attributes->get(self::class.':stale', false);
    }
}
