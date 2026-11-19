<?php

namespace Tests\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Omatech\Editora\Connector\Cache\CachedExtractor;
use Omatech\Editora\Connector\Cache\ContentFreshness;
use Omatech\Editora\Connector\Cache\PageCache;
use Omatech\Editora\Connector\DoctrineConnection;
use Tests\TestCase;

/**
 * editora:warm visits only the pages without a current copy, so the content it finds is past its 60-second TTL whenever it
 * visits one. A visitor gets such content at once and it is refreshed after the response, which leaves the page unstored
 * (STALE); the warm reads it again before it is served instead, so every pass stores the pages it visits. Each page reads its
 * own content, as every Editora page reads its own object. Copies live a week, and a pass renders them all again a day
 * before they expire. With --if-changed a pass runs only once a publication, a publishing date or a deploy changed the
 * pages, and until a pass stored every page it could.
 */
final class WarmedPagesTest extends TestCase
{
    /** 2026-11-17 10:00:00 UTC. */
    private const START = 1794909600;
    private const PAGES = ['http://localhost/ca', 'http://localhost/ca/about'];

    private Connection $content;

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
        // The home (instance 1) is warmed as /ca; Tests\Fixtures\PreviewPage makes Preview_Page objects pages.
        DB::table('omp_classes')->insert([['id' => 1, 'name' => 'Home', 'tag' => 'Home'], ['id' => 2, 'name' => 'Page', 'tag' => 'Preview_Page']]);
        foreach ([[1, 1, 'Welcome', 'home'], [10, 2, 'About', 'about']] as [$id, $class, $name, $niceurl]) {
            DB::insert("INSERT INTO omp_instances VALUES (?, ?, ?, 'O', '2025-11-17 10:00:00', NULL, '2026-11-16 10:00:00')", [$id, $class, $name]);
            DB::table('omp_niceurl')->insert(['inst_id' => $id, 'language' => 'ca', 'niceurl' => $niceurl]);
        }
        config(['app.url' => 'http://localhost', 'editora.availableLanguages' => ['ca'], 'editora.homeNiceUrl' => false,
            'editora.controllersNamespace' => 'Tests\\Fixtures\\']);
        $this->app->forgetInstance(ContentFreshness::class);
        ContentFreshness::register($this->app);
        $this->app->make(HttpKernel::class)->pushMiddleware(PageCache::class);
        Route::get('/ca/{slug?}', fn (?string $slug = null) => response('<!doctype html><html><body>'.$this->names($slug === null ? 1 : 2).'</body></html>'));
        $this->at(0);
    }

    /** A new HTTP client, as each run has, whose requests reach this application with the headers the warm sends. */
    private function site(): void
    {
        Http::swap(new HttpFactory($this->app['events']));
        Http::fake(function (ClientRequest $request) {
            $this->app->forgetScopedInstances();
            $response = $this->get($request->url(), array_map(fn (array $values) => $values[0], $request->headers()));

            return Http::response($response->getContent(), $response->getStatusCode(), $response->headers->all());
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Moves both clocks to START + $seconds; whatever comes next is a new request. */
    private function at(int $seconds): void
    {
        $now = self::START + $seconds;
        Carbon::setTestNow(Carbon::createFromTimestamp($now));
        DB::statement('SET TIMESTAMP = '.$now);
        $this->content->executeStatement('SET TIMESTAMP = '.$now);
        $this->app->forgetScopedInstances();
        $this->app->instance('request', Request::create('/'));
        $this->site();
    }

    /** What the page of class $class shows, read through the cached extractor as the application registers it. */
    private function names(int $class): string
    {
        $extractor = new CachedExtractor($this->content, Cache::store(), 60, 'test',
            ['lang' => 'ca', 'metadata' => true, 'extract_values' => false], 86400, CachedExtractor::METHODS, $this->app->make(ContentFreshness::class));

        return implode(',', array_map(fn (array $instance) => $instance['metadata']['nom_intern'], $extractor->findInstancesInClass($class)));
    }

    /** One editora:warm pass at START + $seconds: its summary from the status counts on. */
    private function warm(int $seconds): string
    {
        $this->at($seconds);
        self::assertSame(0, Artisan::call('editora:warm'));
        $lines = explode("\n", trim(Artisan::output()));
        self::assertMatchesRegularExpression('/^2 pages in [\d.]+ s\. Status: .+$/D', $summary = end($lines));

        return preg_replace('/^2 pages in [\d.]+ s\. /', '', $summary);
    }

    /** One editora:warm --if-changed run at START + $seconds: the URLs it visited. */
    private function ifChanged(int $seconds): array
    {
        $this->at($seconds);
        self::assertSame(0, Artisan::call('editora:warm', ['--if-changed' => true]));

        return Http::recorded()->map(fn (array $pair) => $pair[0]->url())->values()->all();
    }

    /** A page of class 2 whose publishing_begins is $begins, a Unix time read in the session time zone, as NOW() is. */
    private function page(int $id, string $name, string $niceurl, int $begins): void
    {
        DB::insert("INSERT INTO omp_instances VALUES (?, 2, ?, 'O', FROM_UNIXTIME(?), NULL, '2026-11-16 10:00:00')", [$id, $name, $begins]);
        DB::table('omp_niceurl')->insert(['inst_id' => $id, 'language' => 'ca', 'niceurl' => $niceurl]);
    }

    private function visit(int $seconds, array $headers = []): TestResponse
    {
        $this->at($seconds);

        return $this->get('/ca/about', $headers)->assertOk()->assertSee('<body>About</body>', false);
    }

    public function test_a_pass_renders_every_page_again_a_day_before_the_copies_expire(): void
    {
        $day = 86400;
        self::assertSame('Status: 200: 2. Page cache: MISS: 2. Already stored: 0. Visited: 2.', $this->warm(0));
        // Both copies live a week (PAGE_CACHE_TTL): the passes that find them send nothing.
        self::assertSame('Status: none. Page cache: none. Already stored: 2. Visited: 0.', $this->warm(600));
        self::assertSame('Status: none. Page cache: none. Already stored: 2. Visited: 0.', $this->warm(6 * $day - 1));
        // The record of that pass lasts six days: this pass renders both pages again, from content read six days ago.
        self::assertSame('Status: 200: 2. Page cache: MISS: 2. Already stored: 0. Visited: 2.', $this->warm(6 * $day));
        // A week after the first pass, its copies would have expired; the visitor gets the copy of the last one.
        $this->visit(7 * $day + 60)->assertHeader('X-Page-Cache', 'HIT');
    }

    public function test_if_changed_warms_only_after_a_publication_a_publishing_date_or_a_deploy(): void
    {
        self::assertSame(self::PAGES, $this->ifChanged(0));
        self::assertSame([], $this->ifChanged(60));
        // An editor publishes: no stored copy has the new key.
        $this->at(120);
        $this->app->make(ContentFreshness::class)->published();
        self::assertSame(self::PAGES, $this->ifChanged(180));
        self::assertSame([], $this->ifChanged(240));
        // A page enters the site when its publishing_begins passes, with no write; the pass in that second stores nothing (STALE).
        $this->page(11, 'News', 'news', self::START + 300);
        $pages = [...self::PAGES, 'http://localhost/ca/news'];
        self::assertSame($pages, $this->ifChanged(300));
        self::assertStringContainsString('Page cache: STALE: 3.', Artisan::output());
        self::assertSame($pages, $this->ifChanged(301));
        self::assertStringContainsString('Page cache: MISS: 3.', Artisan::output());
        self::assertSame([], $this->ifChanged(360));
        $this->deploy();
        self::assertSame($pages, $this->ifChanged(420));
        self::assertSame([], $this->ifChanged(480));
    }

    public function test_a_visitor_never_waits_for_content_past_its_ttl_even_with_a_forged_warm_header(): void
    {
        // Copies of an hour, so that the visits below find the content, not the page, past its TTL.
        config(['editora-cache.page.ttl' => 3600]);
        $this->visit(0)->assertHeader('X-Page-Cache', 'MISS');
        $this->visit(3600)->assertHeader('X-Page-Cache', 'STALE');
        $this->visit(7200, ['X-Editora-Warm' => 'forged'])->assertHeader('X-Page-Cache', 'STALE');
        // Each of those visits refreshed the content after its response.
        $this->visit(7201)->assertHeader('X-Page-Cache', 'MISS');
    }
}
