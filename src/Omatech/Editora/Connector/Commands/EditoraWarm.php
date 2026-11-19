<?php

namespace Omatech\Editora\Connector\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Omatech\Editora\Connector\Cache\ContentFreshness;
use Omatech\Editora\Connector\Cache\PageCache;
use Omatech\Editora\Connector\EditoraController;

/**
 * Visits the published pages that PageCache holds no copy of, through the site's own URL, so the content, static text and
 * page caches are warm before visitors arrive: an empty content cache costs the first visitor a full render. A page whose
 * copy is stored under the current key is skipped without a request (PageCache::stored()). Content past its TTL, as a pass
 * usually finds it, is read again before the answer (ContentFreshness::WARM_HEADER), so the page is stored.
 *
 * With --if-changed nothing is listed or visited while PageCache::generation() (the build, CMS_CACHE_VERSION and the last
 * publication or publishing date passed) is what the last complete pass recorded: a deploy, a publication or a publishing
 * date starts a pass. A pass is complete when no page failed (5xx or no answer) and none was answered STALE, as content read
 * in the second of a publication leaves it; it records its generation in the page store for the page TTL, so a pass also
 * runs once the copies it stored may have expired. Schedule `editora:warm --if-changed` every minute and a plain
 * `editora:warm`, which fills whatever has no copy, twice a day (README, "Warming the caches").
 *
 * Only pages the site serves are visited (an object of a class without a page controller answers 404), and of the
 * classes in editora.publicationClasses (news, posts, events...) only those published in the last
 * editora-cache.warm.recent_months months: an older publication is cached when someone visits it, instead of
 * taking every pass and the page cache with years of rarely read pages.
 */
final class EditoraWarm extends Command
{
    /** Editora's home is instance 1 (see EditoraController). */
    private const HOME = 1;

    /** Followed by the hash of the base URL: the generation the last complete pass of that site warmed. */
    private const STATE = 'editora-cache:warmed:';

    protected $signature = 'editora:warm
        {--url= : Base URL of the site to visit (default: APP_URL)}
        {--if-changed : Visit nothing unless a deploy, a publication or a publishing date changed the pages since the last complete pass}';

    protected $description = 'Visit the published Editora pages without a stored copy to warm the content and page caches';

    public function handle(PageCache $pages): int
    {
        $base = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        $generation = $pages->generation();
        $store = Cache::store(config('editora-cache.page.store'));
        $state = self::STATE.hash('sha256', $base);
        if ($this->option('if-changed') && $this->unchanged($store->get($state), $generation)) {
            return self::SUCCESS;
        }
        $started = microtime(true);
        [$visits, $stored] = $this->pass($base, $pages);
        $this->summarize($visits, $stored, microtime(true) - $started);

        $failures = array_filter($visits, fn (array $visit) => $visit[1] === 0 || $visit[1] >= 500);
        if ($failures === [] && !in_array('STALE', array_column($visits, 2), true)) {
            $this->record($store, $state, $generation);
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    /** Whether the pages are as the last complete pass left them; says so, or what changed. */
    private function unchanged(mixed $recorded, array $generation): bool
    {
        if ($recorded === $generation) {
            $this->info('Unchanged since the last complete pass: no page visited.');

            return true;
        }
        $this->line(is_array($recorded)
            ? 'Changed since the last complete pass: '.implode(', ', array_keys(array_diff_assoc($generation, $recorded))).'.'
            : 'No complete pass on record.');

        return false;
    }

    /** Kept as long as the copies the pass stored: once they may have expired, --if-changed visits them again. */
    private function record(Repository $store, string $state, array $generation): void
    {
        $ttl = (int) config('editora-cache.page.ttl');
        $store->put($state, $generation, $ttl > 0 ? $ttl : null);
    }

    /**
     * Visits, one after another, the pages without a stored copy.
     *
     * @return array{0: list<array{0: string, 1: int, 2: string}>, 1: int} the visits and how many pages had a copy
     */
    private function pass(string $base, PageCache $pages): array
    {
        $visits = [];
        $stored = 0;
        foreach ($this->paths() as $path) {
            if ($pages->stored($base.$path)) {
                $stored++;
            } else {
                $visits[] = $this->visit($base.$path);
            }
        }

        return [$visits, $stored];
    }

    private function summarize(array $visits, int $stored, float $seconds): void
    {
        foreach ($visits as [$url, $status]) {
            if ($status === 0 || $status >= 400) {
                $this->warn(sprintf('  %s %s', $status ?: 'no answer', $url));
            }
        }
        $this->info(sprintf('%d pages in %.1f s. Status: %s. Page cache: %s. Already stored: %d. Visited: %d.', $stored + count($visits),
            $seconds, $this->counts(array_column($visits, 1)), $this->counts(array_column($visits, 2)), $stored, count($visits)));
    }

    /**
     * Home pages first, in the order of editora.availableLanguages, then the published nice URLs of the pages to warm.
     *
     * @return list<string>
     */
    private function paths(): array
    {
        $languages = config('editora.availableLanguages');
        $homeNiceUrl = config('editora.homeNiceUrl') === true;
        $homes = $homeNiceUrl ? [] : array_combine($languages, array_map(fn (string $language) => '/'.$language, $languages));
        $pages = [];

        foreach ($this->publishedNiceUrls($languages) as $row) {
            $path = '/'.$row->language.'/'.rawurlencode((string) $row->niceurl);
            if ((int) $row->inst_id !== self::HOME) {
                if (EditoraController::pageController((string) $row->tag) !== null) {
                    $pages[] = $path;
                }
            } elseif ($homeNiceUrl) {
                $homes[$row->language] = $path;
            }
        }

        $orderedHomes = array_filter(array_map(fn (string $language) => $homes[$language] ?? null, $languages));

        return [...array_values($orderedHomes), ...$pages];
    }

    private function publishedNiceUrls(array $languages): iterable
    {
        $now = $this->databaseNow();
        $publications = array_values((array) config('editora.publicationClasses', []));
        $since = $now->copy()->subMonths((int) config('editora-cache.warm.recent_months'))->toDateTimeString();
        $now = $now->toDateTimeString();

        return DB::table('omp_niceurl as n')
            ->join('omp_instances as i', 'i.id', '=', 'n.inst_id')
            ->join('omp_classes as c', 'c.id', '=', 'i.class_id')
            ->where('i.status', 'O')
            ->where(fn ($query) => $query->whereNull('i.publishing_begins')->orWhere('i.publishing_begins', '<=', $now))
            ->where(fn ($query) => $query->whereNull('i.publishing_ends')->orWhere('i.publishing_ends', '>', $now))
            ->where(fn ($query) => $query->whereNotIn('c.tag', $publications)->orWhere('i.publishing_begins', '>=', $since))
            ->whereIn('n.language', $languages)
            ->orderBy('n.language')->orderBy('n.niceurl')
            ->get(['n.inst_id', 'n.language', 'n.niceurl', 'c.tag']);
    }

    /**
     * Now on the wall clock the publishing dates are written in and compared with: the session zone of the connection
     * (the editors' zone that DatabaseTimezone sets on MySQL), else the application's. Compared with the application's
     * UTC, a page would join the pass that its own publishing date starts an hour late (two in summer).
     */
    private function databaseNow(): Carbon
    {
        $zone = DB::connection()->getConfig('timezone');

        return Carbon::now(is_string($zone) && $zone !== '' ? $zone : null);
    }

    /** @return array{0: string, 1: int, 2: string} The URL, its HTTP status (0 without an answer) and X-Page-Cache. */
    private function visit(string $url): array
    {
        try {
            $response = Http::timeout((int) config('editora-cache.warm.timeout'))->withUserAgent('editora-warm')
                ->withHeaders([ContentFreshness::WARM_HEADER => ContentFreshness::warmToken()])->get($url);

            return [$url, $response->status(), $response->header('X-Page-Cache') ?: 'none'];
        } catch (ConnectionException) {
            return [$url, 0, 'none'];
        }
    }

    /** "200: 120, 404: 1" from a list of values; "none" from an empty one. */
    private function counts(array $values): string
    {
        $counts = array_count_values(array_map('strval', $values));
        ksort($counts);

        return $counts === [] ? 'none' : implode(', ', array_map(fn ($key, int $count) => "$key: $count", array_keys($counts), $counts));
    }
}
