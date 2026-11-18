<?php

namespace Omatech\Editora\Connector\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Omatech\Editora\Connector\EditoraController;

/**
 * Visits the published pages once, through the site's own URL, so the content, static text and page caches
 * are warm before visitors arrive: an empty content cache costs the first visitor a full render, and content
 * past its TTL is refreshed after the answer. Run it on a schedule and after changing CMS_CACHE_VERSION.
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

    protected $signature = 'editora:warm {--url= : Base URL of the site to visit (default: APP_URL)}';

    protected $description = 'Visit the published Editora pages once to warm the content and page caches';

    public function handle(): int
    {
        $base = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        $started = microtime(true);
        $visits = array_map(fn (string $path) => $this->visit($base.$path), $this->paths());

        foreach ($visits as [$url, $status]) {
            if ($status === 0 || $status >= 400) {
                $this->warn(sprintf('  %s %s', $status ?: 'no answer', $url));
            }
        }
        $this->info(sprintf('%d pages in %.1f s. Status: %s. Page cache: %s.', count($visits), microtime(true) - $started,
            $this->counts(array_column($visits, 1)), $this->counts(array_column($visits, 2))));

        $failures = array_filter($visits, fn (array $visit) => $visit[1] === 0 || $visit[1] >= 500);

        return $failures === [] ? self::SUCCESS : self::FAILURE;
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
            $path = '/'.$row->language.'/'.rawurlencode($row->niceurl);
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
        $now = Carbon::now();
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

    /** @return array{0: string, 1: int, 2: string} The URL, its HTTP status (0 without an answer) and X-Page-Cache. */
    private function visit(string $url): array
    {
        try {
            $response = Http::timeout((int) config('editora-cache.warm.timeout'))->withUserAgent('editora-warm')->get($url);

            return [$url, $response->status(), $response->header('X-Page-Cache') ?: 'none'];
        } catch (ConnectionException) {
            return [$url, 0, 'none'];
        }
    }

    /** "200: 120, 404: 1" from a list of values. */
    private function counts(array $values): string
    {
        $counts = array_count_values(array_map('strval', $values));
        ksort($counts);

        return implode(', ', array_map(fn ($key, int $count) => "$key: $count", array_keys($counts), $counts));
    }
}
