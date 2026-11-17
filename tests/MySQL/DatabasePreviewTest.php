<?php

namespace Tests\MySQL;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class DatabasePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('CONNECTOR_TEST_MYSQL') !== '1') $this->markTestSkipped('Use make mysql-check with the owned synthetic MySQL fixture.');
        self::assertSame('mysql', config('database.connections.mysql.host'));
        self::assertSame('connector_fixture', config('database.connections.mysql.database'));
        $this->preparePreviewRoutes();
        foreach (['omp_niceurl', 'omp_instances', 'omp_classes'] as $table) DB::statement('DROP TABLE IF EXISTS '.$table);
        DB::statement('CREATE TABLE omp_classes (id INT PRIMARY KEY, tag VARCHAR(80))');
        DB::statement('CREATE TABLE omp_instances (id INT PRIMARY KEY, class_id INT, key_fields VARCHAR(80), status CHAR(1), publishing_begins DATETIME, publishing_ends DATETIME NULL)');
        DB::statement('CREATE TABLE omp_niceurl (inst_id INT, language VARCHAR(10), niceurl VARCHAR(80))');
        DB::table('omp_classes')->insert(['id' => 1, 'tag' => 'preview_page']);
        $this->page(42, 'published', 'O', '2020-01-01', null);
        $this->page(43, 'pending', 'P', '2020-01-01', null);
        $this->page(44, 'expired', 'O', '2020-01-01', '2020-01-02');
        $this->page(45, 'future', 'O', '2099-01-01', null);
    }

    private function page(int $id, string $slug, string $status, string $begins, ?string $ends): void
    {
        DB::table('omp_instances')->insert(['id' => $id, 'class_id' => 1, 'key_fields' => 'SYNTHETIC '.$slug,
            'status' => $status, 'publishing_begins' => $begins, 'publishing_ends' => $ends]);
        DB::table('omp_niceurl')->insert(['inst_id' => $id, 'language' => 'ca', 'niceurl' => $slug]);
    }

    private function pageRequest(string $path)
    {
        // FPM constructs a controller per request; the in-process kernel otherwise reuses its first URL.
        foreach (Route::getRoutes()->getRoutes() as $route) $route->flushController();

        return $this->get($path);
    }

    public function test_publication_rules_apply_to_actual_mysql_urls(): void
    {
        self::assertStringStartsWith('8.4.', DB::selectOne('SELECT VERSION() version')->version);
        $this->pageRequest('/ca/published')->assertOk()->assertJson(['preview' => false, 'id' => 42]);
        foreach (['pending', 'expired', 'future'] as $slug) $this->pageRequest('/ca/'.$slug)->assertNotFound();
    }

    public function test_anonymous_preview_cannot_render_actual_nonpublic_rows(): void
    {
        foreach (['pending', 'expired', 'future'] as $slug) $this->pageRequest('/ca/'.$slug.'?req_info=1')->assertNotFound();
    }

    public function test_editors_can_explicitly_preview_actual_nonpublic_rows(): void
    {
        $this->withSession(['user_id' => 7]);
        foreach (['pending' => 43, 'expired' => 44, 'future' => 45] as $slug => $id) {
            $this->pageRequest('/ca/'.$slug.'?req_info=1')->assertOk()->assertJson(['preview' => true, 'id' => $id]);
        }
    }

    public function test_an_editor_session_does_not_make_a_pending_url_public(): void
    {
        $this->withSession(['user_id' => 7])->get('/ca/pending')->assertNotFound();
    }
}
