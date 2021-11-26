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
 * Common ancestor for stream handlers (file, phar, http)
 * Track stream handler and stream filter registrations
 */
class StreamHandler
{

    public const NAME = 'stream';

    public const OPEN = 0x1;
    public const CLOSE = 0x2;
    public const LOCK = 0x4;
    public const READ = 0x8;
    public const WRITE = 0x10;
    public const TRUNCATE = 0x20;
    public const FLUSH = 0x40;
    public const SEEK = 0x80;
    public const UNLINK = 0x100;
    public const RENAME = 0x200;
    public const SET = 0x400;
    public const STAT = 0x800;
    public const META = 0x1000;
    public const INFO = 0x2000;

    public const OPEN_DIR = 0x4000;
    public const READ_DIR = 0x8000;
    public const REWIND_DIR = 0x10000;
    public const CLOSE_DIR = 0x20000;
    public const MAKE_DIR = 0x40000;
    public const REMOVE_DIR = 0x80000;
    public const CHANGE_DIR = 0x100000;

    public const FILES = 0x1FFF; // without info
    public const DIRS = 0x1FC000;
    public const ALL = 0x1FFFFF;
    public const NONE = 0;

    protected const INCLUDE_FLAGS = 16512;

    /** @var int */
    private static $interceptHandlers = Intercept::NONE;

    /** @var int */
    private static $interceptFilters = Intercept::NONE;

    /**
     * Takes control over stream_wrapper_register(), stream_wrapper_unregister() and stream_wrapper_restore()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptHandlers(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'stream_wrapper_register', [self::class, 'fakeRegister']);
        Intercept::register(self::NAME, 'stream_wrapper_unregister', [self::class, 'fakeUnregister']);
        Intercept::register(self::NAME, 'stream_wrapper_restore', [self::class, 'fakeRestore']);
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
        Intercept::register(self::NAME, 'stream_filter_register', [self::class, 'fakeFilterRegister']);
        Intercept::register(self::NAME, 'stream_filter_remove', [self::class, 'fakeFilterRemove']);
        Intercept::register(self::NAME, 'stream_filter_append', [self::class, 'fakeFilterAppend']);
        Intercept::register(self::NAME, 'stream_filter_prepend', [self::class, 'fakeFilterPrepend']);
        self::$interceptFilters = $level;
    }

    public static function fakeRegister(string $protocol, string $class, int $flags = 0): bool
    {
        return Intercept::handle(self::NAME, self::$interceptHandlers, 'stream_wrapper_register', [$protocol, $class, $flags], true);
    }

    public static function fakeUnregister(string $protocol): bool
    {
        return Intercept::handle(self::NAME, self::$interceptHandlers, 'stream_wrapper_unregister', [$protocol], true);
    }

    public static function fakeRestore(string $protocol): bool
    {
        return Intercept::handle(self::NAME, self::$interceptHandlers, 'stream_wrapper_restore', [$protocol], true);
    }

    public static function fakeFilterRegister(string $filter_name, string $class): bool
    {
        return Intercept::handle(self::NAME, self::$interceptFilters, 'stream_filter_register', [$filter_name, $class], true);
    }

    /**
     * @param resource $stream_filter
     */
    public static function fakeFilterRemove($stream_filter): bool
    {
        return Intercept::handle(self::NAME, self::$interceptFilters, 'stream_filter_remove', [$stream_filter], true);
    }

    /**
     * @param resource $stream
     * @param mixed $params
     * @return resource|false
     */
    public static function fakeFilterAppend($stream, string $filter_name, int $mode, $params)
    {
        return Intercept::handle(self::NAME, self::$interceptFilters, 'stream_filter_append', [$stream, $filter_name, $mode, $params], false);
    }

    /**
     * @param resource $stream
     * @param mixed $params
     * @return resource|false
     */
    public static function fakeFilterPrepend($stream, string $filter_name, int $mode, $params)
    {
        return Intercept::handle(self::NAME, self::$interceptFilters, 'stream_filter_prepend', [$stream, $filter_name, $mode, $params], null);
    }

}
