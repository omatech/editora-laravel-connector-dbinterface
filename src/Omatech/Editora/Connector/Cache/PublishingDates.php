<?php

namespace Omatech\Editora\Connector\Cache;

use Illuminate\Database\Connection;

/**
 * When the publishing dates of published objects pass. Editora's publication filter shows an object from its publishing_begins
 * and hides it from its publishing_ends, compared with the database's NOW(), so public content changes then without any
 * write. The seconds are counted by the database clock and in the session time zone that filter compares in, on the
 * application's default connection, which CachedExtractor::register() reads the content from.
 *
 * @internal Read by ContentFreshness, which keeps the result in the content store.
 */
final class PublishingDates
{
    private const SQL = 'SELECT UNIX_TIMESTAMP() observed_at,'
        .' TIMESTAMPDIFF(SECOND, NOW(), MAX(IF(moment <= NOW(), moment, NULL))) passed,'
        .' TIMESTAMPDIFF(SECOND, NOW(), MIN(IF(moment > NOW(), moment, NULL))) next'
        ." FROM (SELECT publishing_begins moment FROM omp_instances WHERE status = 'O'"
        ." UNION ALL SELECT publishing_ends FROM omp_instances WHERE status = 'O') dates";

    /** The publication filter is MySQL's: no other database holds dates it compares. */
    public static function readable(Connection $connection): bool
    {
        return in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * @return array{observed_at: int, passed: ?int, next: ?int} the MySQL timestamp and the offsets from that same second
     */
    public static function around(Connection $connection): array
    {
        $row = $connection->selectOne(self::SQL);

        return ['observed_at' => (int) $row->observed_at, 'passed' => self::seconds($row->passed), 'next' => self::seconds($row->next)];
    }

    private static function seconds(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
