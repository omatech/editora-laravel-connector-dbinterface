<?php

namespace Tests\MySQL;

use Doctrine\DBAL\DriverManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Omatech\Editora\Connector\Cache\CachedExtractor;
use Omatech\Editora\Connector\Cache\ContentFreshness;
use Omatech\Editora\Connector\DoctrineConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Editors type publishing dates in Madrid time and Editora stores them with no zone, while Laravel Cloud's MySQL runs in UTC:
 * compared with a UTC NOW(), an object ended an hour late in winter and two in summer. Every connection a request opens,
 * Laravel's and the Doctrine ones of the extractor, Utils and Loader, reads NOW() in the editors' zone. MySQL keeps the clock
 * of a test session with SET TIMESTAMP; the request boots with Carbon at the same instant.
 */
final class EditorsTimezoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('CONNECTOR_TEST_MYSQL') !== '1') $this->markTestSkipped('Use make mysql-check with the owned synthetic MySQL fixture.');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{int, string}> a UTC instant and the Madrid wall clock an editor reads then */
    public static function instants(): array
    {
        return ['winter (CET)' => [1794909600, '2026-11-17 11:00:00'], 'summer (CEST)' => [1784282400, '2026-07-17 12:00:00']];
    }

    /** A request booting at $utc: the connections it opens from then on. */
    private function requestAt(int $utc): void
    {
        Carbon::setTestNow(Carbon::createFromTimestamp($utc));
        $this->refreshApplication();
    }

    private function contentConnection(int $utc): \Doctrine\DBAL\Connection
    {
        $content = DriverManager::getConnection(DoctrineConnection::fromLaravel(DB::connection()->getConfig()));
        $content->executeStatement('SET TIMESTAMP = '.$utc);

        return $content;
    }

    #[DataProvider('instants')]
    public function test_every_connection_reads_now_in_the_editors_time_zone(int $utc, string $madrid): void
    {
        $this->requestAt($utc);
        DB::statement('SET TIMESTAMP = '.$utc);
        self::assertSame($madrid, DB::selectOne('SELECT NOW() AS now')->now);
        self::assertSame($madrid, $this->contentConnection($utc)->fetchOne('SELECT NOW()'));
    }

    public function test_an_object_ends_at_the_madrid_time_its_editor_typed(): void
    {
        $this->requestAt(1794909600);
        foreach (['omp_niceurl', 'omp_instances', 'omp_classes'] as $table) DB::statement('DROP TABLE IF EXISTS '.$table);
        DB::statement('CREATE TABLE omp_classes (id INT PRIMARY KEY, name VARCHAR(80), tag VARCHAR(80))');
        DB::statement('CREATE TABLE omp_instances (id INT PRIMARY KEY, class_id INT, key_fields VARCHAR(80), status CHAR(1), publishing_begins DATETIME, publishing_ends DATETIME NULL, update_date DATETIME)');
        DB::statement('CREATE TABLE omp_niceurl (inst_id INT, language VARCHAR(10), niceurl VARCHAR(80))');
        DB::table('omp_classes')->insert(['id' => 1, 'name' => 'News', 'tag' => 'news']);
        // As the admin stores what the editor typed: "ends 17/11/2026 at 11:00", Madrid time, no zone.
        DB::table('omp_instances')->insert(['id' => 1, 'class_id' => 1, 'key_fields' => 'Ending at eleven', 'status' => 'O',
            'publishing_begins' => '2026-01-01 00:00:00', 'publishing_ends' => '2026-11-17 11:00:00', 'update_date' => '2026-01-01 00:00:00']);
        $listed = function (int $utc): array {
            $this->requestAt($utc);
            DB::statement('SET TIMESTAMP = '.$utc);
            $extractor = new CachedExtractor($this->contentConnection($utc), Cache::store(), 60, 'test',
                ['lang' => 'ca', 'metadata' => true, 'extract_values' => false], 86400, CachedExtractor::METHODS, new ContentFreshness(Cache::store()));

            return array_map(fn (array $instance) => $instance['metadata']['nom_intern'], $extractor->findInstancesInClass(1));
        };

        // 10:59:59 in Madrid (09:59:59 UTC): still published. 11:00:00 in Madrid (10:00:00 UTC): ended.
        self::assertSame(['Ending at eleven'], $listed(1794909600 - 1));
        self::assertSame([], $listed(1794909600));
    }
}
