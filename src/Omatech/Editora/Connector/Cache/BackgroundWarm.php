<?php

namespace Omatech\Editora\Connector\Cache;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Starts editora:warm --if-changed in the background when a request finds the pages changed since the last pass: a page
 * without a current copy (after a deploy, a publication or a publishing date, or once copies expired) or a publication
 * recorded by the request itself. It runs after the response, so nobody waits for it, as a separate process (WarmProcess).
 * Nothing polls for changes: a site that nobody visits or edits runs nothing and can sleep.
 *
 * A process is started at most once every RETRY seconds for a generation, so a pass that keeps failing is not started by
 * every visit; the pass holds a lock, and catches up with what changes while it runs, so passes never overlap.
 */
final class BackgroundWarm
{
    private const STARTED = 'editora-cache:warm-started:';
    private const RETRY = 600;

    public function __construct(private WarmProcess $process)
    {
    }

    /** After the response of this request, starts a pass unless the last one completed the current generation. */
    public function whenChanged(): void
    {
        $request = request();
        if (!config('editora-cache.warm.on_change', true) || $request->attributes->has(self::class)) {
            return;
        }
        $request->attributes->set(self::class, true);
        // The callbacks of every request are run again by each later terminate() of a long-lived application, such as a test.
        $done = false;
        app()->terminating(function () use (&$done): void {
            if ($done) {
                return;
            }
            $done = true;
            try {
                $this->startIfChanged();
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    private function startIfChanged(): void
    {
        $generation = app(PageCache::class)->generation();
        $store = Cache::store(config('editora-cache.page.store'));
        $base = WarmState::base();
        if (WarmState::complete($store->get(WarmState::key($base)), $generation)) {
            return;
        }
        if ($store->add(self::STARTED.hash('sha256', json_encode([$base, $generation], JSON_THROW_ON_ERROR)), true, self::RETRY)) {
            $this->process->start();
        }
    }
}
