<?php

namespace Omatech\Editora\Connector\Cache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Whether cached CMS content is still current. A publication is recorded, as a time, whenever the Editora
 * admin writes public content; content cached before it stops being fresh, so it is served once more and
 * refreshed after the response, and no visitor waits for a cold render. In reload mode (editora-cache.publications
 * = reload, or = editors for browsers marked by PageCache as Editora admin sessions) it is read again before it is
 * served instead, so the first visit already shows the publication. A
 * page rendered from content that was not fresh must not be stored. Unlike a CacheVersion change, a publication
 * discards nothing.
 *
 * A refresh that read the database before a publication and stored after it keeps serving that content
 * for at most one TTL, and no page rendered from it is stored meanwhile.
 *
 * A publishing date that passes changes public content too, without any write: the publication filter shows an object from
 * its publishing_begins and hides it from its publishing_ends. With a store and a MySQL application database, the last such
 * date counts as a publication at that second, and content read up to it is read again before it is served in every mode, so
 * no copy outlives the date (PublishingDates). The dates are read from the database once and kept in the store until the next
 * one passes, a publication is recorded or DATES_TTL goes by; every other request reads them with the publication, in the
 * same round trip to the store.
 *
 * editora:warm visits a page only when the page cache holds no current copy of it, so the content it finds is usually past
 * its TTL. Served at once, that content would leave every warmed page unstored; requests carrying WARM_HEADER with
 * warmToken() read it again before it is served instead, so the warm stores the pages it visits. Only the application's key
 * yields the token: a visitor never waits for that read.
 *
 * Every publication starts editora:warm in the background once the response is sent (BackgroundWarm), so the site is warm
 * again shortly after an editor saves, with nothing polling for changes.
 */
final class ContentFreshness
{
    private const KEY = 'editora-cache:published-at';
    /** The publishing dates as last read: when they were read, the last one passed (0 for none) and the next (null for none). */
    private const DATES = 'editora-cache:publishing-dates';
    private const NO_DATES = ['checked' => 0, 'passed' => 0, 'next' => null];
    /** Dates written without a recorded publication, as console imports do, are read within this many seconds. */
    private const DATES_TTL = 60;
    private const ADMIN = 'Omatech\Editora\Admin\Controller';
    /** The header editora:warm sends, with warmToken() as its value. */
    public const WARM_HEADER = 'X-Editora-Warm';
    /** Admin tables that no public page reads: sign-in, favourites, recent items, roles and permissions. */
    private const PRIVATE_TABLES = ['omp_users', 'omp_user_instances', 'omp_roles', 'omp_roles_classes', 'omp_instances_roles'];
    private const WRITE = '/^\s*(?:INSERT|REPLACE|UPDATE|DELETE|TRUNCATE|ALTER|DROP|CREATE|RENAME)\b/i';
    private const TARGET = '/^\s*(?:(?:INSERT|REPLACE)(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE))*(?:\s+INTO)?'
        .'|UPDATE(?:\s+(?:LOW_PRIORITY|IGNORE))*|DELETE(?:\s+(?:LOW_PRIORITY|QUICK|IGNORE))*\s+FROM)\s+`?(\w+)`?[\s(]/i';

    /** A null store means publications are not tracked: content is fresh for its whole TTL. */
    public function __construct(private ?Repository $store = null, private bool $reload = false, private bool $warm = false)
    {
    }

    /**
     * Records a publication after every public write made by an Editora admin request. All admin writes go
     * through the Laravel connection, so saves, publication, deletion, cloning, relations, ordering, static
     * texts, images and imports count alike; visitor requests and console commands never do.
     */
    public static function register(Application $app): void
    {
        $app->scoped(self::class, fn ($app) => new self(
            $app['cache']->store(config('editora-cache.content.store')),
            self::reloads($app['request']),
            self::warms($app['request']),
        ));
        $app['events']->listen(QueryExecuted::class, function (QueryExecuted $query) use ($app): void {
            if ($app['request']->route()?->getControllerClass() !== self::ADMIN || !self::changesPublicContent($query->sql)) {
                return;
            }
            try {
                $app->make(self::class)->published();
            } catch (Throwable $e) {
                // The admin's write is already committed and the rest of its statements must still run.
                report($e);
            }
        });
    }

    /** Whether this request reads content again after a publication, per editora-cache.publications. */
    private static function reloads(Request $request): bool
    {
        return match (config('editora-cache.publications')) {
            'reload' => true,
            'editors' => $request->cookies->has(PageCache::EDITOR_COOKIE),
            default => false,
        };
    }

    /** The value of WARM_HEADER for this application: derived from its key, so only its own editora:warm sends it. */
    public static function warmToken(): string
    {
        return hash_hmac('sha256', 'editora:warm', (string) config('app.key'));
    }

    /** Whether this is a request of editora:warm: it reads stale content again and renders the page instead of replaying a copy. */
    public function warming(): bool
    {
        return $this->warm;
    }

    private static function warms(Request $request): bool
    {
        $token = $request->headers->get(self::WARM_HEADER);

        return is_string($token) && (string) config('app.key') !== '' && hash_equals(self::warmToken(), $token);
    }

    public static function changesPublicContent(string $sql): bool
    {
        if (!preg_match(self::WRITE, $sql)) {
            return false;
        }

        return !preg_match(self::TARGET, $sql, $match) || !in_array(strtolower($match[1]), self::PRIVATE_TABLES, true);
    }

    /** Time of the last publication or publishing date passed, read once per request; 0 when publications are not tracked. */
    public function lastPublication(): int
    {
        $passed = $this->dates()['passed'];

        return max(request()->attributes->get(self::KEY), $passed);
    }

    /** Time of the last publication recorded (an admin write or editora:published), without publishing dates; 0 for none. */
    public function lastRecordedPublication(): int
    {
        $this->dates();

        return (int) request()->attributes->get(self::KEY);
    }

    /** Seconds that what is cached now may be kept, at most $ttl: never past the next publishing date, nothing while it is unknown. */
    public function lifetime(int $ttl): int
    {
        $next = $this->dates()['next'];

        return $next === null ? $ttl : max(0, min($ttl, $next - Carbon::now()->getTimestamp()));
    }

    /** The publishing dates of this request, read with the last publication, in one round trip to the store, once. */
    private function dates(): array
    {
        $attributes = request()->attributes;
        if (!$attributes->has(self::DATES)) {
            $stored = $this->store === null ? [] : [...$this->store->getMultiple([self::KEY, self::DATES])];
            if (!$attributes->has(self::KEY)) {
                $attributes->set(self::KEY, (int) ($stored[self::KEY] ?? 0));
            }
            $attributes->set(self::DATES, $this->currentDates($stored[self::DATES] ?? null, $attributes->get(self::KEY)));
        }

        return $attributes->get(self::DATES);
    }

    /** The stored dates while no date passed and no publication followed them; otherwise read again. */
    private function currentDates(mixed $stored, int $published): array
    {
        $database = $this->store === null ? null : app('db')->connection();
        if (!$database instanceof Connection || !PublishingDates::readable($database)) {
            return self::NO_DATES;
        }
        $now = Carbon::now()->getTimestamp();
        if (is_array($stored) && $stored['checked'] > $published && ($stored['next'] ?? PHP_INT_MAX) > $now) {
            return $stored;
        }

        return $this->readDates($database, $now);
    }

    private function readDates(Connection $database, int $now): array
    {
        try {
            $around = PublishingDates::around($database);
        } catch (Throwable $e) {
            report($e);
            // Unknown for this request only: content keeps its own rules and no page is stored.
            return ['checked' => $now, 'passed' => 0, 'next' => $now];
        }
        $dates = ['checked' => $now, 'passed' => $around['passed'] === null ? 0 : $now + $around['passed'],
            'next' => $around['next'] === null ? null : $now + $around['next']];
        $this->store->put(self::DATES, $dates, min(self::DATES_TTL, ($dates['next'] ?? PHP_INT_MAX) - $now));

        return $dates;
    }

    /**
     * Records a publication now, at most once per second and request, and warms the site once this request ends (after its
     * response, for a web request); a failure throws.
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
        if (!$this->store->forever(self::KEY, $now)) {
            throw new RuntimeException('The publication could not be recorded.');
        }
        request()->attributes->set(self::KEY, $now);
        app(BackgroundWarm::class)->whenChanged();
    }

    /** Forgets the publication and dates this request read, so the next call reads them again: for a long-running command. */
    public function forget(): void
    {
        request()->attributes->remove(self::KEY);
        request()->attributes->remove(self::DATES);
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

    /**
     * Whether content read at $readAt for a $ttl-second cache must be read again before it is served: once a publishing date
     * followed the read, in reload mode once a publication did, and for editora:warm once it is not fresh.
     */
    public function mustReload(int $readAt, int $ttl): bool
    {
        if ($this->warm && !$this->fresh($readAt, $ttl)) {
            return true;
        }
        $after = $this->reload ? $this->lastPublication() : $this->dates()['passed'];

        return $after > 0 && $readAt <= $after;
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
