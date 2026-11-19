<?php

return [
    // Part of every content and page cache key: changing it stops serving every older copy.
    'version' => env('CMS_CACHE_VERSION', 'v1'),

    // After a publication, content read before it is served once more and refreshed after the response ('refresh'),
    // or read again before it is served, so the first visit already shows the publication: for every request
    // ('reload') or only for browsers with an Editora admin session ('editors'), whose read updates it for everyone.
    'publications' => env('CMS_CACHE_PUBLICATIONS', 'refresh'),

    // Extractor graphs cached by CachedExtractor; a null store means the application's default store.
    'content' => [
        'enabled' => env('CMS_CACHE_ENABLED', true),
        'ttl' => (int) env('CMS_CACHE_TTL', 60),
        'stale_ttl' => (int) env('CMS_CACHE_STALE_TTL', 86400),
        'store' => env('CMS_CACHE_STORE'),
    ],

    // editora:warm, which visits the published pages without a current copy to fill the caches. The site starts it in the
    // background when a request finds the pages changed since the last pass (on_change): nothing polls for changes, so an
    // idle site can sleep. Of the classes in editora.publicationClasses (news, posts, events...) it visits only those
    // published in the last recent_days days.
    'warm' => [
        'timeout' => (int) env('CMS_CACHE_WARM_TIMEOUT', 60),
        'recent_days' => 7,
        'on_change' => env('CMS_CACHE_WARM_ON_CHANGE', true),
    ],

    // Whole public pages served by the PageCache middleware, one copy per URL. A copy lives ttl seconds (a week), never past
    // the next publishing date; a deploy, a publication, a publishing date or a new version makes it obsolete at once, and
    // the next copy of the URL replaces it. A dated publication older than warm.recent_days is kept publication_ttl seconds
    // (a day) after a visit stores it. Anything else a page shows (rows written outside the Editora admin, such as indexers
    // and imports) changes only with the TTL, unless what writes it records a publication (editora:published).
    'page' => [
        'enabled' => env('PAGE_CACHE_ENABLED', true),
        'store' => env('PAGE_CACHE_STORE'),
        'ttl' => (int) env('PAGE_CACHE_TTL', 604800),
        'publication_ttl' => (int) env('PAGE_CACHE_PUBLICATION_TTL', 86400),
        // A larger page is not stored. There is no limit on the bytes of the store: its Valkey autoscales.
        'max_bytes' => 524288,
    ],
];
