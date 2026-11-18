<?php

namespace Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Omatech\Editora\Connector\Cache\PageCache;

/**
 * The page cache limits pages (what may be shared, at most editora-cache.page.max_bytes each, for its TTL and never past
 * a publishing date), not the bytes of the cache: the Valkey behind it autoscales, so a page is stored however many bytes
 * other pages took before it.
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
}
