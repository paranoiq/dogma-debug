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

    public const NAME = 'request';

    /** @var bool Print request headers */
    public static $requestHeaders = false;

    /** @var bool Print request body */
    public static $requestBody = false;

    /** @var bool Print response headers */
    public static $responseHeaders = false;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var int */
    private static $interceptHeaders = Intercept::NONE;

    /** @var int */
    private static $interceptCookies = Intercept::NONE;

    /**
     * Take control over header(), header_remove(), ignore_user_abort()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptHeaders(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'header', [self::class, 'fakeHeader']);
        Intercept::register(self::NAME, 'header_remove', [self::class, 'fakeHeaderRemove']);
        Intercept::register(self::NAME, 'header_register_callback', [self::class, 'fakeHeaderRegister']);
        Intercept::register(self::NAME, 'http_response_code', [self::class, 'fakeResponseCode']);
        self::$interceptHeaders = $level;
    }

    /**
     * Take control over setcookie() and setrawcookie()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptCookies(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'setcookie', [self::class, 'fakeCookie']);
        Intercept::register(self::NAME, 'setrawcookie', [self::class, 'fakeRawCookie']);
        self::$interceptCookies = $level;
    }

    public static function fakeHeader(string $header, bool $replace = true, int $responseCode = 0): void
    {
        Intercept::handle(self::NAME, self::$interceptHeaders, 'header', [$header, $replace, $responseCode], null);
    }

    public static function fakeHeaderRemove(?string $header = null): void
    {
        Intercept::handle(self::NAME, self::$interceptHeaders, 'header_remove', [$header], null);
    }

    public static function fakeHeaderRegister(callable $callable): bool
    {
        return Intercept::handle(self::NAME, self::$interceptHeaders, 'header_register_callback', [$callable], true);
    }

    /**
     * @return bool|int
     */
    public static function fakeResponseCode(?int $code = null)
    {
        if ($code === null) {
            return http_response_code();
        }

        return Intercept::handle(self::NAME, self::$interceptHeaders, 'http_response_code', [$code], true);
    }

    public static function fakeCookie(string $name, string $value = ''/* ... */): bool
    {
        return Intercept::handle(self::NAME, self::$interceptCookies, 'setcookie', func_get_args(), true);
    }

    public static function fakeRawCookie(string $name, string $value = ''/* ... */): bool
    {
        return Intercept::handle(self::NAME, self::$interceptCookies, 'setrawcookie', func_get_args(), true);
    }

}
