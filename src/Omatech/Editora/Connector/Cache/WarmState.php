<?php

namespace Omatech\Editora\Connector\Cache;

/**
 * What the last editora:warm pass of a site recorded in the page store: the generation it warmed (PageCache::generation()),
 * whether it was complete (every page answered, none STALE) and when the last pass that visited every page started. A pass
 * of the current generation leaves only pages without a copy to visit; a complete one leaves nothing. The record lasts a day
 * less than the copies (page TTL) from that full pass, however many passes follow, so a pass renders every page again
 * before the copies of the full pass expire.
 */
final class WarmState
{
    private const KEY = 'editora-cache:warm-pass:';

    /** The base URL editora:warm visits by default and the site starts it for: APP_URL. */
    public static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function key(string $base): string
    {
        return self::KEY.hash('sha256', rtrim($base, '/'));
    }

    /** Whether $recorded is a pass of $generation, complete or not. */
    public static function current(mixed $recorded, array $generation): bool
    {
        return is_array($recorded) && ($recorded['generation'] ?? null) === $generation;
    }

    /** Whether $recorded is a complete pass of $generation: nothing is left to warm. */
    public static function complete(mixed $recorded, array $generation): bool
    {
        return self::current($recorded, $generation) && ($recorded['complete'] ?? false) === true;
    }

    /** @return array{generation: array, complete: bool, rendered: int} */
    public static function of(array $generation, bool $complete, int $rendered): array
    {
        return ['generation' => $generation, 'complete' => $complete, 'rendered' => $rendered];
    }

    /** When the last pass that visited every page of $recorded started; null for none. */
    public static function rendered(mixed $recorded): ?int
    {
        return is_array($recorded) && is_int($recorded['rendered'] ?? null) ? $recorded['rendered'] : null;
    }

    /** Seconds a record lasts after its full pass started: a day less than the copies, and at least half their TTL. */
    public static function lifetime(int $ttl): int
    {
        return max(intdiv($ttl, 2), $ttl - 86400);
    }
}
