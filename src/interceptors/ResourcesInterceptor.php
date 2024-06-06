<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function array_unshift;
use function microtime;
use function min;

/**
 * Monitors system resources (time, memory)
 */
class ResourcesInterceptor
{

    public const NAME = 'resources';

    /** @var int */
    private static $interceptTicks = Intercept::NONE;

    /** @var int */
    private static $interceptTimeLimit = Intercept::NONE;

    /** @var int */
    private static $interceptSleep = Intercept::NONE;

    /** @var int */
    private static $interceptGc = Intercept::NONE;

    /**
     * Take control over register_tick_function() and unregister_tick_function()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptTicks(int $level = Intercept::LOG_CALLS): void
    {
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for tick related functions.");
        }

        Intercept::registerFunction(self::NAME, 'register_tick_function', self::class);
        Intercept::registerFunction(self::NAME, 'unregister_tick_function', self::class);
        self::$interceptTicks = $level;
    }

    /**
     * Take control over set_time_limit()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptTimeLimit(int $level = Intercept::LOG_CALLS): void
    {
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for set_time_limit.");
        }

        Intercept::registerFunction(self::NAME, 'set_time_limit', self::class);
        self::$interceptTimeLimit = $level;
    }

    /**
     * Take control over sleep(), usleep(), time_nanosleep() and time_sleep_until()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptSleep(int $level = Intercept::LOG_CALLS): void
    {
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for sleep functions.");
        }

        Intercept::registerFunction(self::NAME, 'sleep', self::class);
        Intercept::registerFunction(self::NAME, 'usleep', self::class);
        Intercept::registerFunction(self::NAME, 'time_nanosleep', self::class);
        Intercept::registerFunction(self::NAME, 'time_sleep_until', self::class);
        self::$interceptSleep = $level;
    }

    /**
     * Takes control over gc_enable(), gc_disable(), gc_collect_cycles() and gc_mem_caches()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptGc(int $level = Intercept::LOG_CALLS): void
    {
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for garbage collection related functions.");
        }

        Intercept::registerFunction(self::NAME, 'gc_enable', self::class);
        Intercept::registerFunction(self::NAME, 'gc_disable', self::class);
        Intercept::registerFunction(self::NAME, 'gc_collect_cycles', self::class);
        Intercept::registerFunction(self::NAME, 'gc_mem_caches', self::class);

        self::$interceptGc = $level;
    }

    // decorators ------------------------------------------------------------------------------------------------------

    /**
     * @param mixed ...$args
     */
    public static function register_tick_function(callable $callback, ...$args): bool
    {
        if (Intercept::$wrapEventHandlers & Intercept::EVENT_TICK) {
            $callback = Intercept::wrapEventHandler($callback, Intercept::EVENT_TICK);
        }

        array_unshift($args, $callback);

        return Intercept::handle(self::NAME, self::$interceptTicks, __FUNCTION__, $args, true);
    }

    public static function unregister_tick_function(callable $callback): void
    {
        Intercept::handle(self::NAME, self::$interceptTicks, __FUNCTION__, [$callback], null);
    }

    public static function set_time_limit(int $seconds): bool
    {
        if (!(self::$interceptTimeLimit & Intercept::PREVENT_CALLS)) {
            ResourcesHandler::$extendedTime += Resources::timeUsed();
        }

        return Intercept::handle(self::NAME, self::$interceptTimeLimit, __FUNCTION__, [$seconds], true);
    }

    /**
     * @return int|false
     */
    public static function sleep(int $seconds)
    {
        if (!(self::$interceptSleep & Intercept::PREVENT_CALLS)) {
            ResourcesHandler::$sleptTime += $seconds;
        }

        return Intercept::handle(self::NAME, self::$interceptSleep, __FUNCTION__, [$seconds], 0);
    }

    public static function usleep(int $microseconds): void
    {
        if (!(self::$interceptSleep & Intercept::PREVENT_CALLS)) {
            ResourcesHandler::$sleptTime += ($microseconds / 1000000);
        }

        Intercept::handle(self::NAME, self::$interceptSleep, __FUNCTION__, [$microseconds], null);
    }

    /**
     * @return bool|int[]
     */
    public static function time_nanosleep(int $seconds, int $nanoseconds)
    {
        if (!(self::$interceptSleep & Intercept::PREVENT_CALLS)) {
            ResourcesHandler::$sleptTime += $seconds + ($nanoseconds / 1000000000);
        }

        return Intercept::handle(self::NAME, self::$interceptSleep, __FUNCTION__, [$seconds, $nanoseconds], true);
    }

    public static function time_sleep_until(float $timestamp): bool
    {
        if (!(self::$interceptSleep & Intercept::PREVENT_CALLS)) {
            ResourcesHandler::$sleptTime += min(0, $timestamp - microtime(true));
        }

        return Intercept::handle(self::NAME, self::$interceptSleep, __FUNCTION__, [$timestamp], true);
    }

    public static function gc_enable(): void
    {
        Intercept::handle(self::NAME, self::$interceptGc, __FUNCTION__, [], null);
    }

    public static function gc_disable(): void
    {
        Intercept::handle(self::NAME, self::$interceptGc, __FUNCTION__, [], null);
    }

    public static function gc_collect_cycles(): int
    {
        return Intercept::handle(self::NAME, self::$interceptGc, __FUNCTION__, [], 0);
    }

    public static function gc_mem_caches(): int
    {
        return Intercept::handle(self::NAME, self::$interceptGc, __FUNCTION__, [], 0);
    }

}
