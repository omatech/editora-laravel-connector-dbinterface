<?php

namespace Omatech\Editora\Connector\Media;

/** Which CMS media paths the site may serve, and how, according to editora-media. */
final class PublicMedia
{
    public static function allows(string $path): bool
    {
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || str_starts_with($segment, '.') || str_contains($segment, '\\') || str_contains($segment, "\0")) {
                return false;
            }
        }
        if (in_array(strtolower(explode('/', $path)[0]), config('editora-media.denied_prefixes'), true)
            || in_array(self::extension($path), config('editora-media.denied_extensions'), true)) {
            return false;
        }

        return self::type($path) !== null || config('editora-media.attachments');
    }

    /** Inline content type of the path, or null when it may only be downloaded. */
    public static function type(string $path): ?string
    {
        return config('editora-media.types')[self::extension($path)] ?? null;
    }

    public static function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /** Disk path of the media, below the configured prefix. */
    public static function key(string $path): string
    {
        return ltrim(config('editora-media.prefix').'/'.$path, '/');
    }
}
