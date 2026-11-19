<?php

namespace Omatech\Editora\Connector\Cache;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dated publications: the objects of the classes listed in editora.publicationClasses (news, posts, events...). Those whose
 * publishing_begins falls in the last editora-cache.warm.recent_days days are warmed, and their pages kept, like any other
 * page until they stop being recent. An older one is never warmed: a visit stores its page for
 * editora-cache.page.publication_ttl seconds, after which it leaves the cache unless someone visits it again.
 */
final class Publications
{
    private const RECENT_DAYS = 7;
    private const PUBLICATION_TTL = 86400;

    /** @return list<string> the class tags (omp_classes.tag) of dated publications */
    public static function classes(): array
    {
        return array_values(array_filter(array_map('strval', (array) config('editora.publicationClasses', []))));
    }

    /**
     * Now on the wall clock the publishing dates are written in and compared with: the session zone of the connection (the
     * editors' zone that DatabaseTimezone sets on MySQL), else the application's.
     */
    public static function now(): Carbon
    {
        $zone = DB::connection()->getConfig('timezone');

        return Carbon::now(is_string($zone) && $zone !== '' ? $zone : null);
    }

    /** The earliest publishing_begins of a recent publication, on that clock. */
    public static function recentSince(): Carbon
    {
        return self::now()->subDays(self::recentDays());
    }

    /**
     * Seconds a copy of /$language/$niceurl may be kept, at most $ttl. A dated publication is kept until it stops being
     * recent, and at least publication_ttl, which is all an older one gets when a visit stores it; any other page gets $ttl.
     */
    public static function lifetime(string $language, string $niceurl, int $ttl): int
    {
        $classes = self::classes();
        if ($classes === []) {
            return $ttl;
        }
        $page = DB::table('omp_niceurl as n')
            ->join('omp_instances as i', 'i.id', '=', 'n.inst_id')
            ->join('omp_classes as c', 'c.id', '=', 'i.class_id')
            ->where('n.language', $language)->where('n.niceurl', $niceurl)
            ->first(['c.tag', 'i.publishing_begins']);
        if ($page === null || !in_array((string) $page->tag, $classes, true)) {
            return $ttl;
        }
        $configured = config('editora-cache.page.publication_ttl');
        $minimum = min($ttl, is_numeric($configured) ? (int) $configured : self::PUBLICATION_TTL);
        if ($page->publishing_begins === null) {
            return $minimum;
        }
        $now = self::now();
        $recentUntil = Carbon::parse((string) $page->publishing_begins, $now->getTimezone())->addDays(self::recentDays());

        return max($minimum, min($ttl, $recentUntil->getTimestamp() - $now->getTimestamp()));
    }

    /** An application config/editora-cache.php written for an earlier version may define 'warm' without recent_days. */
    private static function recentDays(): int
    {
        $days = config('editora-cache.warm.recent_days');

        return is_numeric($days) ? (int) $days : self::RECENT_DAYS;
    }
}
