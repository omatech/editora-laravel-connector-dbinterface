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

    // editora:warm, which visits the published pages to fill the caches. Of the classes in editora.publicationClasses
    // (news, posts, events...) it visits only those published in the last recent_months months.
    'warm' => [
        'timeout' => (int) env('CMS_CACHE_WARM_TIMEOUT', 60),
        'recent_months' => 3,
    ],

    // Whole public pages served by the PageCache middleware.
    'page' => [
        'enabled' => env('PAGE_CACHE_ENABLED', true),
        'store' => env('PAGE_CACHE_STORE'),
        'ttl' => (int) env('PAGE_CACHE_TTL', 3600),
        // A larger page is not stored. There is no limit on the bytes of the store: its Valkey autoscales.
        'max_bytes' => 524288,
    ],
];
