<?php

namespace Omatech\Editora\Connector\Cache;

/**
 * The pages editora:warm visits. The connector binds PublishedPages; an application whose public URLs are not the nice
 * URLs of its objects (a product under /{language}/{category}/{subcategory}/{product}, say) binds its own, and may build on
 * PublishedPages::pages(). Its deeper paths are cached when editora-cache.page.max_depth allows them.
 */
interface WarmPaths
{
    /**
     * Paths under the site's base URL, each starting with '/', homes first.
     *
     * @return iterable<string>
     */
    public function paths(): iterable;
}
