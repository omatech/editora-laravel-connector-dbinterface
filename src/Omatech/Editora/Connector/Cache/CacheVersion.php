<?php

namespace Omatech\Editora\Connector\Cache;

/** The part of every content and page cache key that a publication or a deliberate reset changes. */
interface CacheVersion
{
    public function current(): string;
}
