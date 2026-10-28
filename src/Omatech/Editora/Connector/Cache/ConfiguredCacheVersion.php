<?php

namespace Omatech\Editora\Connector\Cache;

final class ConfiguredCacheVersion implements CacheVersion
{
    public function current(): string
    {
        return (string) config('editora-cache.version');
    }
}
