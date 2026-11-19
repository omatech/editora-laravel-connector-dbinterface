<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as AppRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Omatech\Editora\Connector\Cache\ContentFreshness;
use Omatech\Editora\Connector\Cache\PageCache;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * editora:warm visits only the pages the site serves: an object of a class without a page controller answers 404 (see
 * EditoraController), and of the classes in editora.publicationClasses (news, posts, events...) only those published in
 * the last editora-cache.warm.recent_months months. An older publication is cached when someone visits it.
 *
 * A page whose copy PageCache already holds is not visited. With --if-changed nothing is visited, or even listed, until a
 * deploy, a CMS_CACHE_VERSION, a publication or a publishing date changed the pages since the last pass without failures.
 */
final class EditoraWarmTest extends TestCase
{
    private const PAGES = ['https://site.test/ca', 'https://site.test/ca/about', 'https://site.test/ca/old-news', 'https://site.test/ca/recent-news'];

    /** @var array<string, int> the status a URL answers instead of 200; 0 for no answer */
    private array $answers = [];

    /** Whether the requests reach this application, with PageCache, instead of $answers. */
    private bool $served = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://site.test', 'editora.availableLanguages' => ['ca'], 'editora.homeNiceUrl' => false,
            'editora.controllersNamespace' => 'Tests\\Fixtures\\']);
        Schema::create('omp_classes', function (Blueprint $table) {
            $table->integer('id');
            $table->string('tag');
        });
        Schema::create('omp_instances', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('class_id');
            $table->string('status');
            $table->dateTime('publishing_begins')->nullable();
            $table->dateTime('publishing_ends')->nullable();
        });
        Schema::create('omp_niceurl', function (Blueprint $table) {
            $table->integer('inst_id');
            $table->string('language');
            $table->string('niceurl')->nullable();
        });
        // Tests\Fixtures\RedirectHeaders and PreviewPage exist; Tests\Fixtures\Fragment does not.
        DB::table('omp_classes')->insert([['id' => 10, 'tag' => 'Home'], ['id' => 20, 'tag' => 'Redirect_Headers'],
            ['id' => 30, 'tag' => 'Preview_Page'], ['id' => 40, 'tag' => 'Fragment']]);
        $instances = [[1, 10, '2015-01-01 00:00:00', 'home'], [2, 20, '2015-01-01 00:00:00', 'about'],
            [3, 30, Carbon::now()->subDays(10)->toDateTimeString(), 'recent-news'],
            [4, 30, Carbon::now()->subMonths(4)->toDateTimeString(), 'old-news'], [5, 40, '2015-01-01 00:00:00', 'fragment']];
        foreach ($instances as [$id, $class, $begins, $niceurl]) {
            DB::table('omp_instances')->insert(['id' => $id, 'class_id' => $class, 'status' => 'O', 'publishing_begins' => $begins]);
            DB::table('omp_niceurl')->insert(['inst_id' => $id, 'language' => 'ca', 'niceurl' => $niceurl]);
        }
        $this->site();
    }

    /** A new HTTP client for the next run, as a new process has: its record of requests starts empty. */
    private function site(): void
    {
        Http::swap(new HttpFactory($this->app['events']));
        Http::fake(fn (Request $request) => $this->served ? $this->serve($request) : $this->answer($request));
    }

    private function answer(Request $request): mixed
    {
        $status = $this->answers[$request->url()] ?? 200;

        return $status === 0 ? Http::failedConnection() : Http::response('<html></html>', $status, ['X-Page-Cache' => 'MISS']);
    }

    /** The warm's request, answered by this application with the headers it sent. */
    private function serve(Request $request): mixed
    {
        $this->app->forgetScopedInstances();
        $response = $this->get($request->url(), array_map(fn (array $values) => $values[0], $request->headers()));

        return Http::response($response->getContent(), $response->getStatusCode(), $response->headers->all());
    }

    /** The pages answer through PageCache, with the status of $answers; /ca/about carries a CSRF field, so no copy of it is ever stored. */
    private function withPageCache(): void
    {
        $this->served = true;
        Route::middleware(PageCache::class)->get('/ca/{slug?}', fn (?string $slug = null) => response($slug === 'about'
            ? '<html><body><form><input type="hidden" name="_token" value="t"></form></body></html>'
            : '<html><body>'.$slug.'</body></html>', $this->answers['https://site.test/ca/'.$slug] ?? 200));
    }

    /** What a later process sees: a new request, so no publication read before is remembered. */
    private function nextRun(): void
    {
        $this->app->forgetScopedInstances();
        $this->app->instance('request', AppRequest::create('/'));
        $this->site();
    }

    public function test_only_recent_publications_and_pages_with_a_controller_are_visited(): void
    {
        config(['editora.publicationClasses' => ['Preview_Page']]);

        self::assertSame(0, Artisan::call('editora:warm'));
        self::assertStringContainsString('3 pages', Artisan::output());

        self::assertSame(['https://site.test/ca', 'https://site.test/ca/about', 'https://site.test/ca/recent-news'], $this->visited());
    }

    public function test_without_publication_classes_every_page_with_a_controller_is_visited(): void
    {
        self::assertSame(0, Artisan::call('editora:warm'));

        self::assertSame(self::PAGES, $this->visited());
    }

    public function test_a_null_legacy_slug_keeps_the_empty_path_segment_without_a_deprecation(): void
    {
        DB::table('omp_niceurl')->where('inst_id', 2)->update(['niceurl' => null]);
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, severity: $severity);
        }, E_DEPRECATED);
        try {
            self::assertSame(0, Artisan::call('editora:warm'));
        } finally {
            restore_error_handler();
        }

        self::assertSame(
            ['https://site.test/ca', 'https://site.test/ca/', 'https://site.test/ca/old-news', 'https://site.test/ca/recent-news'],
            $this->visited(),
        );
    }

    public function test_pages_with_a_stored_copy_are_not_visited(): void
    {
        $this->withPageCache();
        self::assertSame(0, Artisan::call('editora:warm'));
        self::assertStringEndsWith('Status: 200: 4. Page cache: MISS: 4. Already stored: 0. Visited: 4.', trim(Artisan::output()));
        $this->nextRun();

        self::assertSame(0, Artisan::call('editora:warm'));
        self::assertSame(['https://site.test/ca/about'], $this->visited());
        self::assertMatchesRegularExpression('/^4 pages in [\d.]+ s\. Status: 200: 1\. Page cache: MISS: 1\. Already stored: 3\. Visited: 1\.$/D',
            trim(Artisan::output()));
    }

    public function test_if_changed_visits_nothing_while_nothing_changed(): void
    {
        self::assertSame(0, Artisan::call('editora:warm'));
        $this->nextRun();

        self::assertSame(0, Artisan::call('editora:warm', ['--if-changed' => true]));
        Http::assertNothingSent();
        self::assertSame('Unchanged since the last complete pass: no page visited.', trim(Artisan::output()));
    }

    public function test_if_changed_warms_when_no_complete_pass_is_on_record(): void
    {
        self::assertSame(0, Artisan::call('editora:warm', ['--if-changed' => true]));

        self::assertSame(self::PAGES, $this->visited());
        self::assertStringStartsWith('No complete pass on record.', Artisan::output());
    }

    public function test_if_changed_warms_after_a_publication(): void
    {
        $this->app->forgetInstance(ContentFreshness::class);
        ContentFreshness::register($this->app);
        self::assertSame(0, Artisan::call('editora:warm'));
        $this->nextRun();
        $this->app->make(ContentFreshness::class)->published();
        $this->nextRun();

        self::assertSame(0, Artisan::call('editora:warm', ['--if-changed' => true]));
        self::assertSame(self::PAGES, $this->visited());
        self::assertStringStartsWith('Changed since the last complete pass: publication.', Artisan::output());
        $this->nextRun();
        self::assertSame(0, Artisan::call('editora:warm', ['--if-changed' => true]));
        Http::assertNothingSent();
    }

    public function test_if_changed_warms_after_a_deploy(): void
    {
        self::assertSame(0, Artisan::call('editora:warm'));
        $this->deploy();
        $this->nextRun();

        self::assertSame(0, Artisan::call('editora:warm', ['--if-changed' => true]));
        self::assertSame(self::PAGES, $this->visited());
        self::assertStringStartsWith('Changed since the last complete pass: build.', Artisan::output());
    }

    public function test_if_changed_warms_after_a_new_cache_version(): void
    {
        self::assertSame(0, Artisan::call('editora:warm'));
        config(['editora-cache.version' => 'v2']);
        $this->nextRun();

        self::assertSame(0, Artisan::call('editora:warm', ['--if-changed' => true]));
        self::assertSame(self::PAGES, $this->visited());
        self::assertStringStartsWith('Changed since the last complete pass: version.', Artisan::output());
    }

    public function test_after_a_failed_pass_if_changed_visits_only_the_pages_without_a_copy(): void
    {
        $this->withPageCache();
        $this->answers['https://site.test/ca/old-news'] = 500;
        self::assertSame(1, Artisan::call('editora:warm'));
        $this->answers = [];
        $this->nextRun();

        self::assertSame(0, Artisan::call('editora:warm', ['--if-changed' => true]));
        self::assertSame(['https://site.test/ca/about', 'https://site.test/ca/old-news'], $this->visited());
        $this->nextRun();
        self::assertSame(0, Artisan::call('editora:warm', ['--if-changed' => true]));
        Http::assertNothingSent();
    }

    /** @return array<string, array{int, int, list<string>}> what /ca/about answers, the exit code, what the next --if-changed visits */
    public static function passes(): array
    {
        return [
            'a server error' => [500, 1, self::PAGES],
            'no answer' => [0, 1, self::PAGES],
            'not found' => [404, 0, []],
        ];
    }

    /** @param list<string> $visitedNext */
    #[DataProvider('passes')]
    public function test_the_state_is_saved_only_by_a_pass_without_failures(int $status, int $exit, array $visitedNext): void
    {
        $this->answers['https://site.test/ca/about'] = $status;
        self::assertSame($exit, Artisan::call('editora:warm'));
        $this->answers = [];
        $this->nextRun();

        self::assertSame(0, Artisan::call('editora:warm', ['--if-changed' => true]));
        self::assertSame($visitedNext, $this->visited());
    }

    /** @return list<string> */
    private function visited(): array
    {
        return Http::recorded()->map(fn (array $pair) => $pair[0]->url())->values()->all();
    }
}
