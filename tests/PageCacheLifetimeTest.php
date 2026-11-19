<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Omatech\Editora\Connector\Cache\ContentFreshness;
use Omatech\Editora\Connector\Cache\PageCache;
use Omatech\Editora\Connector\Cache\WarmProcess;
use Omatech\Editora\Connector\Cache\WarmState;

/**
 * A copy of a page lives a week; a dated publication (editora.publicationClasses) only while it is recent, and a day after a
 * visit once it is not. One copy per URL: a copy of another generation is never served, and the next one replaces it.
 * A page answered without a current copy starts editora:warm in the background after the response, unless the last pass
 * completed the current generation; so does a publication.
 */
final class PageCacheLifetimeTest extends TestCase
{
    private const DAY = 86400;

    /** How many times a background warm was started. */
    private int $starts = 0;

    /** How many times each page was rendered. */
    private array $renders = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 10));
        config(['app.url' => 'http://localhost', 'editora.availableLanguages' => ['ca'], 'editora-cache.page.store' => 'array',
            'editora.publicationClasses' => ['News']]);
        Schema::create('omp_classes', function (Blueprint $table) {
            $table->integer('id');
            $table->string('tag');
        });
        Schema::create('omp_instances', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('class_id');
            $table->dateTime('publishing_begins')->nullable();
        });
        Schema::create('omp_niceurl', function (Blueprint $table) {
            $table->integer('inst_id');
            $table->string('language');
            $table->string('niceurl');
        });
        DB::table('omp_classes')->insert([['id' => 1, 'tag' => 'Page'], ['id' => 2, 'tag' => 'News']]);
        foreach ([[1, 1, 400, 'about'], [2, 2, 2, 'fresh'], [3, 2, 6.5, 'ending'], [4, 2, 30, 'archive'], [5, 2, null, 'undated']] as [$id, $class, $daysAgo, $niceurl]) {
            DB::table('omp_instances')->insert(['id' => $id, 'class_id' => $class,
                'publishing_begins' => $daysAgo === null ? null : Carbon::now()->subSeconds((int) ($daysAgo * self::DAY))->toDateTimeString()]);
            DB::table('omp_niceurl')->insert(['inst_id' => $id, 'language' => 'ca', 'niceurl' => $niceurl]);
        }
        $starts = &$this->starts;
        $this->app->instance(WarmProcess::class, new class($starts) extends WarmProcess {
            public function __construct(private int &$starts)
            {
            }

            public function start(): void
            {
                $this->starts++;
            }
        });
        Route::middleware(PageCache::class)->get('/ca/{slug?}', function (?string $slug = null) {
            $this->renders[$slug] = ($this->renders[$slug] ?? 0) + 1;

            return response('<html><body>'.$slug.' '.$this->renders[$slug].'</body></html>');
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function visit(string $slug, float $daysLater = 0, array $headers = []): TestResponse
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 10)->addSeconds((int) round($daysLater * self::DAY)));
        $this->app->forgetScopedInstances();

        return $this->get('/ca/'.$slug, $headers);
    }

    /** @return array<string, string> X-Page-Cache of each visit, keyed by the days after the first */
    private function states(string $slug, array $days): array
    {
        $states = [];
        foreach ($days as $day) {
            $states[(string) $day] = $this->visit($slug, $day)->headers->get('X-Page-Cache');
        }

        return $states;
    }

    public function test_a_page_is_kept_a_week(): void
    {
        $justBefore = 7 - 1 / self::DAY;
        self::assertSame(['0' => 'MISS', '6' => 'HIT', (string) $justBefore => 'HIT', '7' => 'MISS'], $this->states('about', [0, 6, $justBefore, 7]));
        self::assertSame(['0' => 'MISS', '6.9' => 'HIT'], $this->states('', [0, 6.9]));
    }

    public function test_a_publication_that_is_no_longer_recent_is_kept_a_day_after_a_visit(): void
    {
        $justBefore = 1 - 1 / self::DAY;
        self::assertSame(['0' => 'MISS', (string) $justBefore => 'HIT', '1' => 'MISS'], $this->states('archive', [0, $justBefore, 1]));
        self::assertSame(['0' => 'MISS', (string) $justBefore => 'HIT', '1' => 'MISS'], $this->states('undated', [0, $justBefore, 1]));
    }

    public function test_a_recent_publication_is_kept_while_it_is_recent_and_at_least_a_day(): void
    {
        // Published two days ago: recent for five more days.
        self::assertSame(['0' => 'MISS', '4.9' => 'HIT', '5' => 'MISS'], $this->states('fresh', [0, 4.9, 5]));
        // Published six and a half days ago: recent for half a day, kept a day.
        self::assertSame(['0' => 'MISS', '0.9' => 'HIT', '1' => 'MISS'], $this->states('ending', [0, 0.9, 1]));
    }

    public function test_without_publication_classes_every_page_is_kept_a_week(): void
    {
        config(['editora.publicationClasses' => []]);

        self::assertSame(['0' => 'MISS', '6.9' => 'HIT', '7' => 'MISS'], $this->states('archive', [0, 6.9, 7]));
    }

    public function test_a_copy_of_another_generation_is_never_served_and_the_next_copy_replaces_it(): void
    {
        self::assertSame(['0' => 'MISS', '0.1' => 'HIT'], $this->states('about', [0, 0.1]));
        config(['editora-cache.version' => 'v2']);
        $this->visit('about', 0.2)->assertHeader('X-Page-Cache', 'MISS')->assertSee('about 2');
        $this->visit('about', 0.3)->assertHeader('X-Page-Cache', 'HIT')->assertSee('about 2');

        $copies = array_filter(Cache::store('array')->getStore()->all(false), fn (string $key) => str_starts_with($key, 'page:'), ARRAY_FILTER_USE_KEY);
        self::assertCount(1, $copies);
    }

    public function test_the_warm_renders_and_stores_a_page_that_has_a_copy(): void
    {
        ContentFreshness::register($this->app);
        $this->visit('about')->assertHeader('X-Page-Cache', 'MISS')->assertSee('about 1');
        $this->visit('about', 0.1, [ContentFreshness::WARM_HEADER => ContentFreshness::warmToken()])->assertHeader('X-Page-Cache', 'MISS')->assertSee('about 2');
        $this->visit('about', 0.2)->assertHeader('X-Page-Cache', 'HIT')->assertSee('about 2');
        // Only the application's token renders the page again.
        $this->visit('about', 0.3, [ContentFreshness::WARM_HEADER => 'forged'])->assertHeader('X-Page-Cache', 'HIT');
    }

    public function test_a_page_without_a_current_copy_starts_one_warm_after_the_response(): void
    {
        $this->visit('about')->assertHeader('X-Page-Cache', 'MISS');
        self::assertSame(1, $this->starts);
        // A copy, or a request that finds a warm already started for this generation: nothing more starts.
        $this->visit('about', 0.001)->assertHeader('X-Page-Cache', 'HIT');
        $this->visit('fresh', 0.002)->assertHeader('X-Page-Cache', 'MISS');
        $this->visit('archive', 0.003)->assertHeader('X-Page-Cache', 'MISS');
        self::assertSame(1, $this->starts);
        // Ten minutes later, while no pass completed this generation, a page without a copy starts one again.
        $this->visit('undated', 600 / self::DAY)->assertHeader('X-Page-Cache', 'MISS');
        self::assertSame(2, $this->starts);
        // An editor's page is never a copy and starts nothing.
        $this->withCookie(PageCache::EDITOR_COOKIE, '1');
        $this->visit('ending', 1200 / self::DAY)->assertHeader('X-Page-Cache', 'BYPASS');
        self::assertSame(2, $this->starts);
    }

    public function test_no_warm_starts_while_the_last_pass_completed_the_current_generation(): void
    {
        $generation = $this->app->make(PageCache::class)->generation();
        Cache::store('array')->put(WarmState::key('http://localhost'), WarmState::of($generation, true, Carbon::now()->getTimestamp()), self::DAY);

        $this->visit('about')->assertHeader('X-Page-Cache', 'MISS');
        self::assertSame(0, $this->starts);
        // An incomplete pass leaves pages to warm.
        Cache::store('array')->put(WarmState::key('http://localhost'), WarmState::of($generation, false, Carbon::now()->getTimestamp()), self::DAY);
        $this->visit('fresh')->assertHeader('X-Page-Cache', 'MISS');
        self::assertSame(1, $this->starts);
    }

    public function test_the_warm_requests_start_no_warm(): void
    {
        ContentFreshness::register($this->app);

        $this->visit('about', 0, [ContentFreshness::WARM_HEADER => ContentFreshness::warmToken()])->assertHeader('X-Page-Cache', 'MISS');
        self::assertSame(0, $this->starts);
    }

    public function test_a_publication_starts_a_warm_once_its_request_ends(): void
    {
        ContentFreshness::register($this->app);
        $this->app->make(ContentFreshness::class)->published();
        self::assertSame(0, $this->starts);

        $this->app->terminate();
        self::assertSame(1, $this->starts);
        $this->app->terminate();
        self::assertSame(1, $this->starts);
    }

    public function test_no_warm_starts_with_warm_on_change_off(): void
    {
        config(['editora-cache.warm.on_change' => false]);

        $this->visit('about')->assertHeader('X-Page-Cache', 'MISS');
        self::assertSame(0, $this->starts);
    }
}
