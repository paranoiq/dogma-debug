<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

/**
 * Track stream wrapper and stream filter registrations
 */
class StreamWrapperInterceptor
{

    public const NAME = 'wrappers';

    /** @var int */
    private static $interceptHandlers = Intercept::NONE;

    /** @var int */
    private static $interceptFilters = Intercept::NONE;

    /**
     * Takes control over stream_wrapper_register(), stream_wrapper_unregister() and stream_wrapper_restore()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptWrappers(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'stream_wrapper_register', self::class);
        Intercept::registerFunction(self::NAME, 'stream_wrapper_unregister', self::class);
        Intercept::registerFunction(self::NAME, 'stream_wrapper_restore', self::class);
        self::$interceptHandlers = $level;
    }

    /**
     * Takes control over stream_filter_register(), stream_filter_remove(), stream_filter_append() and stream_filter_prepend()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptFilters(int $level = Intercept::LOG_CALLS): void
    {
        // @see https://www.php.net/manual/en/class.php-user-filter.php
        Intercept::registerFunction(self::NAME, 'stream_filter_register', self::class);
        Intercept::registerFunction(self::NAME, 'stream_filter_remove', self::class);
        Intercept::registerFunction(self::NAME, 'stream_filter_append', self::class);
        Intercept::registerFunction(self::NAME, 'stream_filter_prepend', self::class);
        self::$interceptFilters = $level;
    }

    public static function stream_wrapper_register(string $protocol, string $class, int $flags = 0): bool
    {
        return Intercept::handle(self::NAME, self::$interceptHandlers, __FUNCTION__, [$protocol, $class, $flags], true);
    }

    public static function stream_wrapper_unregister(string $protocol): bool
    {
        return Intercept::handle(self::NAME, self::$interceptHandlers, __FUNCTION__, [$protocol], true);
    }

    public static function stream_wrapper_restore(string $protocol): bool
    {
        return Intercept::handle(self::NAME, self::$interceptHandlers, __FUNCTION__, [$protocol], true);
    }

    public static function stream_filter_register(string $filter_name, string $class): bool
    {
        return Intercept::handle(self::NAME, self::$interceptFilters, __FUNCTION__, [$filter_name, $class], true);
    }

    /**
     * @param resource $stream_filter
     */
    public static function stream_filter_remove($stream_filter): bool
    {
        return Intercept::handle(self::NAME, self::$interceptFilters, __FUNCTION__, [$stream_filter], true);
    }

    /**
     * @param resource $stream
     * @param mixed $params
     * @return resource|false
     */
    public static function stream_filter_append($stream, string $filter_name, int $mode, $params)
    {
        return Intercept::handle(self::NAME, self::$interceptFilters, __FUNCTION__, [$stream, $filter_name, $mode, $params], false);
    }

    /**
     * @param resource $stream
     * @param mixed $params
     * @return resource|false
     */
    public static function stream_filter_prepend($stream, string $filter_name, int $mode, $params)
    {
        return Intercept::handle(self::NAME, self::$interceptFilters, __FUNCTION__, [$stream, $filter_name, $mode, $params], null);
    }

}
