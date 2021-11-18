<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function func_get_args;
use function http_response_code;

/**
 * Displays request/response headers and request body
 */
class RequestHandler
{

    /** @var bool Print request headers */
    public static $requestHeaders = false;

    /** @var bool Print request body */
    public static $requestBody = false;

    /** @var bool Print response headers */
    public static $responseHeaders = false;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var int */
    private static $takeoverHeaders = Takeover::NONE;

    /** @var int */
    private static $takeoverCookies = Takeover::NONE;

    // takeover handlers -----------------------------------------------------------------------------------------------

    /**
     * Take control over header(), header_remove(), ignore_user_abort()
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeoverHeaders(int $level): void
    {
        Takeover::register('request', 'header', [self::class, 'fakeHeader']);
        Takeover::register('request', 'header_remove', [self::class, 'fakeHeaderRemove']);
        Takeover::register('request', 'header_register_callback', [self::class, 'fakeHeaderRegister']);
        Takeover::register('request', 'http_response_code', [self::class, 'fakeResponseCode']);
        self::$takeoverHeaders = $level;
    }

    /**
     * Take control over setcookie() and setrawcookie()
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeoverCookies(int $level): void
    {
        Takeover::register('request', 'setcookie', [self::class, 'fakeCookie']);
        Takeover::register('request', 'setrawcookie', [self::class, 'fakeRawCookie']);
        self::$takeoverCookies = $level;
    }

    public static function fakeHeader(string $header, bool $replace = true, int $responseCode = 0): void
    {
        Takeover::handle('request', self::$takeoverHeaders, 'header', [$header, $replace, $responseCode], null);
    }

    public static function fakeHeaderRemove(?string $header = null): void
    {
        Takeover::handle('request', self::$takeoverHeaders, 'header_remove', [$header], null);
    }

    public static function fakeHeaderRegister(callable $callable): bool
    {
        return Takeover::handle('request', self::$takeoverHeaders, 'header_register_callback', [$callable], true);
    }

    public static function fakeResponseCode(?int $code = null): bool
    {
        if ($code === null) {
            return http_response_code();
        }

        return Takeover::handle('request', self::$takeoverHeaders, 'http_response_code', [$code], true);
    }

    public static function fakeCookie(string $name, string $value = ''/* ... */): bool
    {
        return Takeover::handle('request', self::$takeoverCookies, 'setcookie', func_get_args(), true);
    }

    public static function fakeRawCookie(string $name, string $value = ''/* ... */): bool
    {
        return Takeover::handle('request', self::$takeoverCookies, 'setrawcookie', func_get_args(), true);
    }

}
