<?php

namespace Omatech\Editora\Connector\Media;

use Illuminate\Support\Facades\Storage;

/** File metadata belongs to the disk that holds the media, including files with no local copy. */
final class FileInfo
{
    public static function describe(string $file): string
    {
        $file = str_starts_with($file, '/') ? substr($file, 1) : $file;
        if (!str_starts_with($file, 'uploads/')) return '';
        $path = substr($file, 8);
        if (!PublicMedia::allows($path)) return '';
        $disk = Storage::disk(config('editora-media.disk', 'editora_public'));
        $key = PublicMedia::key($path);
        if (!$disk->exists($key)) return '';

        return '('.strtoupper(PublicMedia::extension($path)).' '.self::format($disk->size($key)).')';
    }

    private static function format(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $power = $size > 0 ? (int) floor(log($size, 1024)) : 0;

        return number_format($size / (1024 ** $power), 2, '.', ',').$units[$power];
    }
}
