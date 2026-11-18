<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * editora:warm visits only the pages the site serves: an object of a class without a page controller answers 404 (see
 * EditoraController), and of the classes in editora.publicationClasses (news, posts, events...) only those published in
 * the last editora-cache.warm.recent_months months. An older publication is cached when someone visits it.
 */
final class EditoraWarmTest extends TestCase
{
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
            $table->string('niceurl');
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
        Http::fake(fn (Request $request) => Http::response('<html></html>', 200, ['X-Page-Cache' => 'MISS']));
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

        self::assertSame(['https://site.test/ca', 'https://site.test/ca/about', 'https://site.test/ca/old-news', 'https://site.test/ca/recent-news'],
            $this->visited());
    }

    /** @return list<string> */
    private function visited(): array
    {
        return Http::recorded()->map(fn (array $pair) => $pair[0]->url())->values()->all();
    }
}
