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
 * Displays request/response headers, body etc.
 */
class HeadersInterceptor
{

    public const NAME = 'headers';

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
        Intercept::registerFunction(self::NAME, 'header', self::class);
        Intercept::registerFunction(self::NAME, 'header_remove', self::class);
        Intercept::registerFunction(self::NAME, 'header_register_callback', self::class);
        Intercept::registerFunction(self::NAME, 'http_response_code', self::class);
        self::$interceptHeaders = $level;
    }

    /**
     * Take control over setcookie() and setrawcookie()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptCookies(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'setcookie', self::class);
        Intercept::registerFunction(self::NAME, 'setrawcookie', self::class);
        self::$interceptCookies = $level;
    }

    public static function header(string $header, bool $replace = true, int $responseCode = 0): void
    {
        Intercept::handle(self::NAME, self::$interceptHeaders, __FUNCTION__, [$header, $replace, $responseCode], null);
    }

    public static function header_remove(?string $header = null): void
    {
        Intercept::handle(self::NAME, self::$interceptHeaders, __FUNCTION__, [$header], null);
    }

    public static function header_register_callback(callable $callable): bool
    {
        return Intercept::handle(self::NAME, self::$interceptHeaders, __FUNCTION__, [$callable], true);
    }

    /**
     * @return bool|int
     */
    public static function http_response_code(?int $code = null)
    {
        if ($code === null) {
            return http_response_code();
        }

        return Intercept::handle(self::NAME, self::$interceptHeaders, __FUNCTION__, [$code], true);
    }

    public static function setcookie(string $name, string $value = ''/* ... */): bool
    {
        return Intercept::handle(self::NAME, self::$interceptCookies, __FUNCTION__, func_get_args(), true);
    }

    public static function setrawcookie(string $name, string $value = ''/* ... */): bool
    {
        return Intercept::handle(self::NAME, self::$interceptCookies, __FUNCTION__, func_get_args(), true);
    }

}
