<?php

namespace Tests\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Omatech\Editora\Connector\Cache\CachedExtractor;
use Omatech\Editora\Connector\Cache\ContentFreshness;
use Omatech\Editora\Connector\Cache\PageCache;
use Omatech\Editora\Connector\DoctrineConnection;
use Tests\TestCase;

/**
 * An object leaves cached content and cached pages in the second its publishing_ends passes, and enters them in the second its
 * publishing_begins passes, although no write announces it. The database clock follows Carbon's: SET TIMESTAMP fixes NOW() for
 * the extractor's connection and for the application's, which ContentFreshness reads the dates from.
 */
final class PublishingDatesTest extends TestCase
{
    /** 2026-11-17 10:00:00 UTC. */
    private const START = 1794909600;
    private const ENDS = 30;
    private const BEGINS = 90;

    private Connection $content;
    private ContentFreshness $freshness;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('CONNECTOR_TEST_MYSQL') !== '1') $this->markTestSkipped('Use make mysql-check with the owned synthetic MySQL fixture.');
        self::assertSame('connector_fixture', config('database.connections.mysql.database'));
        $this->content = DriverManager::getConnection(DoctrineConnection::fromLaravel(config('database.connections.mysql')));
        foreach (['omp_niceurl', 'omp_instances', 'omp_classes'] as $table) DB::statement('DROP TABLE IF EXISTS '.$table);
        DB::statement('CREATE TABLE omp_classes (id INT PRIMARY KEY, name VARCHAR(80), tag VARCHAR(80))');
        DB::statement('CREATE TABLE omp_instances (id INT PRIMARY KEY, class_id INT, key_fields VARCHAR(80), status CHAR(1), publishing_begins DATETIME, publishing_ends DATETIME NULL, update_date DATETIME)');
        DB::statement('CREATE TABLE omp_niceurl (inst_id INT, language VARCHAR(10), niceurl VARCHAR(80))');
        DB::table('omp_classes')->insert(['id' => 1, 'name' => 'News', 'tag' => 'news']);
        $this->at(0);
        $this->object(10, 'Always', 'O', 0, null);
        $this->object(11, 'Expiring', 'O', 0, self::START + self::ENDS);
        $this->object(12, 'Upcoming', 'O', self::START + self::BEGINS, null);
        // Not public, so its date changes nothing that visitors see.
        $this->object(13, 'Pending', 'P', 0, self::START + 10);
        $this->freshness = new ContentFreshness(Cache::store());
        $this->app->instance(ContentFreshness::class, $this->freshness);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** $begins and $ends are Unix times read in the session time zone, as NOW() is; 0 is long ago. */
    private function object(int $id, string $name, string $status, int $begins, ?int $ends): void
    {
        DB::insert('INSERT INTO omp_instances VALUES (?, 1, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), FROM_UNIXTIME(?))',
            [$id, $name, $status, $begins ?: self::START - 86400 * 365, $ends, self::START - 86400]);
    }

    /** Moves both clocks to START + $seconds; whatever comes next is a new request. */
    private function at(int $seconds): void
    {
        $now = self::START + $seconds;
        Carbon::setTestNow(Carbon::createFromTimestamp($now));
        DB::statement('SET TIMESTAMP = '.$now);
        $this->content->executeStatement('SET TIMESTAMP = '.$now);
        $this->app->instance('request', Request::create('/'));
    }

    /** @return list<string> what the cached extractor lists in class 1 */
    private function names(): array
    {
        $extractor = new CachedExtractor($this->content, Cache::store(), 60, 'test',
            ['lang' => 'ca', 'metadata' => true, 'extract_values' => false], 86400, CachedExtractor::METHODS, $this->freshness);
        $names = array_map(fn (array $instance) => $instance['metadata']['nom_intern'], $extractor->findInstancesInClass(1));
        sort($names);

        return $names;
    }

    /** A visitor's request: what it shows, then what runs after the response. */
    private function visit(): array
    {
        $names = $this->names();
        $this->app->make(DeferredCallbackCollection::class)->invoke();

        return $names;
    }

    private function servePages(): void
    {
        config(['editora.availableLanguages' => ['ca']]);
        $this->app->make(HttpKernel::class)->pushMiddleware(PageCache::class);
        Route::get('/ca/news', fn () => response('<!doctype html><html><body>'.implode(',', $this->names()).'</body></html>'));
    }

    private function page(string $names): TestResponse
    {
        return $this->get('/ca/news')->assertOk()->assertSee('<body>'.$names.'</body>', false);
    }

    /** @return list<int> when each stored page expires */
    private function pageExpiries(): array
    {
        $pages = array_filter(Cache::store()->getStore()->all(false), fn (string $key) => str_starts_with($key, 'page:v2:'), ARRAY_FILTER_USE_KEY);

        return array_values(array_map(fn (array $item) => (int) $item['expiresAt'], $pages));
    }

    public function test_an_object_leaves_cached_content_in_the_second_its_publishing_ends(): void
    {
        self::assertSame(['Always', 'Expiring'], $this->visit());
        $this->at(self::ENDS - 1);
        self::assertSame(['Always', 'Expiring'], $this->visit());
        $this->at(self::ENDS);
        self::assertSame(['Always'], $this->visit());
    }

    public function test_an_object_enters_cached_content_in_the_second_its_publishing_begins(): void
    {
        $this->at(self::BEGINS - 10);
        self::assertSame(['Always'], $this->visit());
        $this->at(self::BEGINS - 1);
        self::assertSame(['Always'], $this->visit());
        $this->at(self::BEGINS);
        self::assertSame(['Always', 'Upcoming'], $this->visit());
    }

    public function test_a_stale_copy_read_before_a_publishing_date_is_never_served_after_it(): void
    {
        self::assertSame(['Always', 'Expiring'], $this->visit());
        // Past its 60 fresh seconds, within its stale day: Cache::flexible() would serve it and refresh it after the response.
        $this->at(self::BEGINS + 600);
        self::assertSame(['Always', 'Upcoming'], $this->names());
        self::assertFalse($this->freshness->stale());
    }

    public function test_a_cached_page_follows_publishing_dates_to_the_second_and_is_cached_again(): void
    {
        $this->servePages();
        $this->page('Always,Expiring')->assertHeader('X-Page-Cache', 'MISS');
        $this->at(self::ENDS - 1);
        $this->page('Always,Expiring')->assertHeader('X-Page-Cache', 'HIT');
        // Content read in the second a date passed is not trusted to have seen it: that page is not stored.
        foreach ([self::ENDS => 'Always', self::BEGINS => 'Always,Upcoming'] as $date => $names) {
            $this->at($date);
            $this->page($names)->assertHeader('X-Page-Cache', 'STALE');
            $this->at($date + 1);
            $this->page($names)->assertHeader('X-Page-Cache', 'MISS');
            $this->at($date + 2);
            $this->page($names)->assertHeader('X-Page-Cache', 'HIT');
        }
    }

    public function test_no_page_is_stored_while_the_dates_cannot_be_read(): void
    {
        config(['editora.availableLanguages' => ['ca']]);
        $this->app->make(HttpKernel::class)->pushMiddleware(PageCache::class);
        Route::get('/ca/static', fn () => response('<!doctype html><html><body>Static</body></html>'));
        DB::statement('RENAME TABLE omp_instances TO omp_instances_unreadable');

        $this->get('/ca/static')->assertOk()->assertHeader('X-Page-Cache', 'MISS');
        $this->at(1);
        $this->get('/ca/static')->assertOk()->assertHeader('X-Page-Cache', 'MISS');
        self::assertSame([], $this->pageExpiries());
    }

    public function test_no_page_is_kept_past_the_next_publishing_date(): void
    {
        $this->servePages();
        $this->page('Always,Expiring')->assertHeader('X-Page-Cache', 'MISS');
        self::assertSame([self::START + self::ENDS], $this->pageExpiries());
        $this->at(self::ENDS + 1);
        $this->page('Always')->assertHeader('X-Page-Cache', 'MISS');
        // The new copy replaced the obsolete one: one copy per URL.
        self::assertSame([self::START + self::BEGINS], $this->pageExpiries());
    }

    public function test_a_day_long_ttl_never_keeps_a_page_past_the_next_publishing_date(): void
    {
        config(['editora-cache.page.ttl' => 86400]);
        $later = self::BEGINS + 7200;
        $this->object(14, 'Later', 'O', 0, self::START + $later);
        $this->servePages();
        $this->at(self::BEGINS + 10);
        $this->page('Always,Later,Upcoming')->assertHeader('X-Page-Cache', 'MISS');
        self::assertSame([self::START + $later], $this->pageExpiries());
        // No date is ahead any more: the copy lives the whole day.
        $this->at($later + 10);
        $this->page('Always,Upcoming')->assertHeader('X-Page-Cache', 'MISS');
        self::assertSame([self::START + $later + 10 + 86400], $this->pageExpiries());
    }

    public function test_the_dates_are_read_once_until_one_passes_an_editor_publishes_or_a_minute_goes_by(): void
    {
        $reads = 0;
        DB::listen(function (QueryExecuted $query) use (&$reads): void {
            $reads += (int) str_contains($query->sql, 'omp_instances');
        });
        $readsAt = function (int $seconds) use (&$reads): int {
            $this->at($seconds);
            $this->visit();

            return $reads;
        };

        self::assertSame([1, 1, 1, 1], array_map($readsAt, [0, 0, 10, self::ENDS - 1]));
        self::assertSame([2, 2], array_map($readsAt, [self::ENDS, self::ENDS + 1]));
        $this->at(40);
        $this->freshness->published();
        self::assertSame([3, 3], array_map($readsAt, [41, 42]));
        self::assertSame([4, 4], array_map($readsAt, [self::BEGINS, self::BEGINS + 59]));
        self::assertSame(5, $readsAt(self::BEGINS + 60));
    }

    public function test_registered_freshness_reads_the_dates_from_the_application_database(): void
    {
        Carbon::setTestNow();
        DB::statement('SET TIMESTAMP = DEFAULT');
        DB::update('UPDATE omp_instances SET publishing_ends = NOW() - INTERVAL 5 SECOND WHERE id = 11');
        $this->app->forgetInstance(ContentFreshness::class);
        ContentFreshness::register($this->app);
        $this->app->instance('request', Request::create('/'));

        self::assertEqualsWithDelta(Carbon::now()->getTimestamp() - 5, $this->app->make(ContentFreshness::class)->lastPublication(), 2);
    }
}
