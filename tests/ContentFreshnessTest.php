<?php

namespace Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Omatech\Editora\Connector\Cache\ContentFreshness;

final class ContentFreshnessTest extends TestCase
{
    /** Editora's publication filter is MySQL's: another application database is never asked for publishing dates. */
    public function test_publishing_dates_are_only_read_from_a_mysql_application_database(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $freshness = new ContentFreshness(Cache::store());

        self::assertSame(0, $freshness->lastPublication());
        self::assertFalse($freshness->mustReload(1, 60));
        self::assertSame(0, $queries);
    }
}
