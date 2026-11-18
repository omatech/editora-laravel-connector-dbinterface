<?php

namespace Tests;

use ErrorException;

/**
 * An empty CMS field reaches the view helpers as null. PHP 8.1+ deprecates passing null to str_replace(), explode(),
 * strip_tags() and stristr(), and Laravel now logs those deprecations (axioma logged _altimaginatortext 200 times in
 * 20 minutes). The helpers answer exactly what PHP answered before, without the deprecation.
 */
final class HelpersNullTextTest extends TestCase
{
    /** @param callable(): mixed $call */
    private static function quietly(callable $call): mixed
    {
        set_error_handler(static fn (int $level, string $message): never => throw new ErrorException($message, 0, $level));
        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }

    public function test_text_helpers_answer_an_empty_string_for_null(): void
    {
        self::assertSame('', self::quietly(static fn () => _altimaginatortext(null)));
        self::assertSame('', self::quietly(static fn () => _alttext(null)));
        self::assertSame('', self::quietly(static fn () => _parsetext(null, 'text', 'list')));
        self::assertSame('', self::quietly(static fn () => _getTextCutted(null, 20)));
    }

    public function test_link_and_video_helpers_keep_their_null_answers(): void
    {
        self::assertSame('http://', self::quietly(static fn () => _link(null)));
        self::assertNull(self::quietly(static fn () => _videolink(null)));
        self::assertNull(self::quietly(static fn () => _videoembed(null)));
        self::assertSame(['lat' => '', 'lng' => null], self::quietly(static fn () => _geolocation(null)));
        self::assertSame(',', self::quietly(static fn () => _mapGeolocation(null)));
    }

    public function test_text_helpers_still_transform_text(): void
    {
        self::assertSame('Alt text', _altimaginatortext('<b>Alt "text"</b>'));
        self::assertSame("It's", _alttext('It"s'));
        self::assertSame('https://www.youtube.com/watch?v=abc', _videolink('youtube:abc'));
        self::assertSame('https://player.vimeo.com/video/42?color=#E9AF2A&portrait=0', _videoembed('vimeo:42'));
        self::assertSame(['lat' => '41.4', 'lng' => '2.1'], _geolocation('41.4:2.1@15'));
    }
}
