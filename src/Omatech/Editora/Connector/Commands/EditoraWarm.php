<?php

namespace Omatech\Editora\Connector\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Omatech\Editora\Connector\Cache\ContentFreshness;
use Omatech\Editora\Connector\Cache\PageCache;
use Omatech\Editora\Connector\Cache\Publications;
use Omatech\Editora\Connector\Cache\WarmState;
use Omatech\Editora\Connector\EditoraController;

/**
 * Visits, through the site's own URL, the published pages without a current copy in PageCache, so the content, static text
 * and page caches are warm before visitors arrive: an empty cache costs the first visitor a full render. Content past its
 * TTL, as a pass usually finds it, is read again before the answer (ContentFreshness::WARM_HEADER), and the page is
 * rendered and stored even when a copy exists.
 *
 * Each pass records its generation (PageCache::generation(): the build, CMS_CACHE_VERSION and the last publication or
 * publishing date passed) and whether it was complete: no page failed (5xx or no answer) or answered STALE (WarmState).
 * When the generation changed since the last pass, every page is visited, since no copy is current; otherwise only the
 * pages without a copy (stored()), and a page that failed loses its copy so the next pass visits it. With --if-changed
 * nothing is listed or visited when the last pass completed the current generation. The record lasts a day less than the
 * copies of the last pass that visited every page, so a pass renders every page again before they expire.
 *
 * The site starts `editora:warm --if-changed` itself, in the background, when a request finds the pages changed
 * (BackgroundWarm): nothing has to poll for changes. Schedule a plain `editora:warm` twice a day for whatever else has no
 * copy (README, "Warming the caches"). Runs never overlap: a run takes a lock for the build and, while it holds it, passes
 * again when a publication or a publishing date changed the pages during its pass; a run that finds the lock taken leaves
 * the work to that one. A pass starts once no publication was recorded in the last SETTLE seconds: saves come in bursts.
 *
 * Only pages the site serves are visited (an object of a class without a page controller answers 404), and of the
 * classes in editora.publicationClasses (news, posts, events...) only those published in the last
 * editora-cache.warm.recent_days days (Publications): an older publication is cached for a day when someone visits it,
 * instead of taking every pass and the page cache with years of rarely read pages.
 */
final class EditoraWarm extends Command
{
    /** Editora's home is instance 1 (see EditoraController). */
    private const HOME = 1;

    /** Passes one run makes at most while the pages keep changing under it. */
    private const PASSES = 3;

    /** Seconds without a publication before a pass starts, waiting at most MAX_SETTLE. */
    private const SETTLE = 15;
    private const MAX_SETTLE = 120;

    /** Bounds a lock that a process ended without releasing, such as one stopped by a deploy. */
    private const LOCK_SECONDS = 1800;

    protected $signature = 'editora:warm
        {--url= : Base URL of the site to visit (default: APP_URL)}
        {--if-changed : Visit nothing when the last pass completed the pages as they are now}
        {--log : Also write what the run did to the application log, for a run in the background}';

    protected $description = 'Visit the published Editora pages without a current copy to warm the content and page caches';

    public function handle(PageCache $pages, ContentFreshness $freshness): int
    {
        $base = rtrim((string) ($this->option('url') ?: WarmState::base()), '/');
        $store = Cache::store(config('editora-cache.page.store'));
        $state = WarmState::key($base);
        $exit = self::SUCCESS;
        for ($round = 0; $round < 2; $round++) {
            $lock = $this->lock($store, $state.':'.$pages->generation()['build']);
            if ($lock !== null && !$lock->get()) {
                $this->say('Another run is warming the site: it also warms what changes meanwhile.');

                return $exit;
            }
            try {
                [$exit, $seen] = $this->passes($base, $store, $state, $pages, $freshness);
            } finally {
                $lock?->release();
            }
            // A change recorded while this run released the lock found it taken and left the work to this run.
            $freshness->forget();
            if ($pages->generation() === $seen) {
                break;
            }
        }

        return $exit;
    }

    private function lock(Repository $store, string $name): ?Lock
    {
        $locks = $store->getStore();

        return $locks instanceof LockProvider ? $locks->lock($name, self::LOCK_SECONDS) : null;
    }

    /**
     * Passes until the pages stop changing under the run, at most PASSES.
     *
     * @return array{0: int, 1: array} the exit code and the generation last looked at
     */
    private function passes(string $base, Repository $store, string $state, PageCache $pages, ContentFreshness $freshness): array
    {
        $exit = self::SUCCESS;
        for ($pass = 0; $pass < self::PASSES; $pass++) {
            $generation = $this->settled($pages, $freshness);
            $recorded = $store->get($state);
            if ($this->option('if-changed') && WarmState::complete($recorded, $generation)) {
                $this->say('Unchanged since the last complete pass: no page visited.');

                return [$exit, $generation];
            }
            $all = !WarmState::current($recorded, $generation);
            $this->say($this->changes($recorded, $generation));
            $started = microtime(true);
            $rendered = $all ? Carbon::now()->getTimestamp() : (WarmState::rendered($recorded) ?? Carbon::now()->getTimestamp());
            [$visits, $stored] = $this->pass($base, $pages, $all);
            $this->summarize($visits, $stored, microtime(true) - $started);

            $failures = array_filter($visits, fn (array $visit) => $visit[1] === 0 || $visit[1] >= 500);
            $incomplete = [...$failures, ...array_filter($visits, fn (array $visit) => $visit[2] === 'STALE')];
            foreach ($incomplete as [$url]) {
                $pages->forget($url);
            }
            $this->record($store, $state, WarmState::of($generation, $incomplete === [], $rendered));
            $exit = $failures === [] ? self::SUCCESS : self::FAILURE;

            $freshness->forget();
            if ($pages->generation() === $generation) {
                return [$exit, $generation];
            }
        }

        return [$exit, $generation];
    }

    /** Keeps $record until a day before the copies of its full pass expire: passes that follow do not extend it. */
    private function record(Repository $store, string $state, array $record): void
    {
        $expires = $record['rendered'] + WarmState::lifetime((int) config('editora-cache.page.ttl'));
        $seconds = $expires - Carbon::now()->getTimestamp();
        $seconds > 0 ? $store->put($state, $record, $seconds) : $store->forget($state);
    }

    /** The generation once no publication was recorded in the last SETTLE seconds, or after MAX_SETTLE; dates do not wait. */
    private function settled(PageCache $pages, ContentFreshness $freshness): array
    {
        $waited = 0;
        while (($quiet = Carbon::now()->getTimestamp() - $freshness->lastRecordedPublication()) < self::SETTLE && $waited < self::MAX_SETTLE) {
            $wait = self::SETTLE - max(0, $quiet);
            Sleep::for($wait)->seconds();
            $waited += $wait;
            $freshness->forget();
        }

        return $pages->generation();
    }

    /** What the pass visits, and why. */
    private function changes(mixed $recorded, array $generation): string
    {
        if (WarmState::current($recorded, $generation)) {
            return $recorded['complete'] ? 'Visiting the pages without a copy.' : 'The last pass was incomplete: visiting the pages without a copy.';
        }
        if (!is_array($recorded) || !is_array($recorded['generation'] ?? null)) {
            return 'No pass on record: visiting every page.';
        }

        return 'Changed since the last pass: '.implode(', ', array_keys(array_diff_assoc($generation, $recorded['generation'])))
            .'. Visiting every page.';
    }

    /**
     * Visits, one after another, every page, or only those without a copy.
     *
     * @return array{0: list<array{0: string, 1: int, 2: string}>, 1: int} the visits and how many pages had a copy
     */
    private function pass(string $base, PageCache $pages, bool $all): array
    {
        $visits = [];
        $stored = 0;
        foreach ($this->paths() as $path) {
            if (!$all && $pages->stored($base.$path)) {
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
                $this->say(sprintf('  %s %s', $status ?: 'no answer', $url), 'warning');
            }
        }
        $this->say(sprintf('%d pages in %.1f s. Status: %s. Page cache: %s. Already stored: %d. Visited: %d.', $stored + count($visits),
            $seconds, $this->counts(array_column($visits, 1)), $this->counts(array_column($visits, 2)), $stored, count($visits)));
    }

    /** Writes $message to the output and, with --log, to the application log. */
    private function say(string $message, string $level = 'info'): void
    {
        $level === 'warning' ? $this->warn($message) : $this->info($message);
        if ($this->option('log')) {
            Log::log($level, 'editora:warm '.trim($message));
        }
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

    /**
     * Published now, on the clock the publishing dates are written in (Publications::now()): compared with the
     * application's UTC, a page would join the pass that its own publishing date starts an hour late (two in summer).
     */
    private function publishedNiceUrls(array $languages): iterable
    {
        $publications = Publications::classes();
        $since = Publications::recentSince()->toDateTimeString();
        $now = Publications::now()->toDateTimeString();

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
