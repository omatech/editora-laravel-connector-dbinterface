<?php

namespace Omatech\Editora\Connector\Cache;

use Illuminate\Support\Facades\DB;
use Omatech\Editora\Connector\EditoraController;

/**
 * The pages editora:warm visits by default: /{language} for each language in editora.availableLanguages (or the home
 * instance's nice URL when homeNiceUrl is true), then /{language}/{nice_url} for every instance published now that is a
 * page, that is, whose class has a page controller (EditoraController::pageController()); of the classes in
 * editora.publicationClasses, only those published in the last editora-cache.warm.recent_days days (Publications).
 */
class PublishedPages implements WarmPaths
{
    /** Editora's home is instance 1 (see EditoraController). */
    private const HOME = 1;

    public function paths(): iterable
    {
        return array_column($this->pages(), 'path');
    }

    /**
     * The pages to warm, homes first, in the order of editora.availableLanguages, with the class tag and the instance of
     * each nice URL (null for a home listed as /{language}).
     *
     * @return list<array{path: string, language: string, tag: ?string, instance: ?int}>
     */
    public function pages(): array
    {
        $languages = config('editora.availableLanguages');
        $homeNiceUrl = config('editora.homeNiceUrl') === true;
        $homes = $homeNiceUrl ? [] : array_combine($languages, array_map(
            fn (string $language) => ['path' => '/'.$language, 'language' => $language, 'tag' => null, 'instance' => null], $languages));
        $pages = [];

        foreach ($this->publishedNiceUrls($languages) as $row) {
            $page = ['path' => '/'.$row->language.'/'.rawurlencode((string) $row->niceurl), 'language' => (string) $row->language,
                'tag' => (string) $row->tag, 'instance' => (int) $row->inst_id];
            if ((int) $row->inst_id !== self::HOME) {
                if (EditoraController::pageController((string) $row->tag) !== null) {
                    $pages[] = $page;
                }
            } elseif ($homeNiceUrl) {
                $homes[$row->language] = $page;
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
}
