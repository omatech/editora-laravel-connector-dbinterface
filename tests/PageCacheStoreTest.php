<?php

namespace Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Omatech\Editora\Connector\Cache\PageCache;

/**
 * The page cache limits pages (what may be shared, at most editora-cache.page.max_bytes each, for its TTL and never past
 * a publishing date), not the bytes of the cache: the Valkey behind it autoscales, so a page is stored however many bytes
 * other pages took before it. editora:warm asks stored() whether a URL has a copy before visiting it.
 */
final class PageCacheStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['editora.availableLanguages' => ['ca'], 'editora-cache.page.store' => 'array']);
        Route::middleware(PageCache::class)->get('/{language}/{slug}', fn () => response('<html><body>page</body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']));
    }

    public function test_a_page_is_stored_whatever_the_bytes_stored_before_it(): void
    {
        // What the byte budget of earlier versions counted for the current hour: far more than its 32 MiB.
        Cache::store('array')->put('page-budget:'.intdiv(time(), 3600), 1 << 30, 7200);

        self::assertSame('MISS', $this->get('/ca/news')->headers->get('X-Page-Cache'));
        self::assertSame('HIT', $this->get('/ca/news')->headers->get('X-Page-Cache'));
    }

    public function test_a_page_larger_than_max_bytes_is_not_stored(): void
    {
        config(['editora-cache.page.max_bytes' => 10]);

        self::assertSame('MISS', $this->get('/ca/large')->headers->get('X-Page-Cache'));
        self::assertSame('MISS', $this->get('/ca/large')->headers->get('X-Page-Cache'));
    }

    public function test_stored_tells_whether_the_next_request_for_a_url_replays_a_copy(): void
    {
        $pages = $this->app->make(PageCache::class);
        self::assertFalse($pages->stored('http://localhost/ca/news'));

        self::assertSame('MISS', $this->get('/ca/news')->headers->get('X-Page-Cache'));
        self::assertTrue($pages->stored('http://localhost/ca/news'));
        // Another page, scheme or host is another copy; a query string or a path PageCache does not serve has none.
        foreach (['http://localhost/ca/other', 'https://localhost/ca/news', 'http://other.test/ca/news',
            'http://localhost/ca/news?page=2', 'http://localhost/uploads/news'] as $url) {
            self::assertFalse($pages->stored($url), $url);
        }
        config(['editora-cache.version' => 'v2']);
        self::assertFalse($pages->stored('http://localhost/ca/news'));
    }
}
