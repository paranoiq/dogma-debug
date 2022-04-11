<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use LogicException;
use function func_get_args;
use function ignore_user_abort;

/**
 * Tracks signals, exit() and die() and tries to determine what lead to process termination
 *
 * PHP request shutdown steps:
 * - call all functions registered via register_shutdown_function()
 * - call all* __destruct() methods
 * - empty all output buffers
 * - end all PHP extensions (e.g. sessions)
 * - turn off output layer (send HTTP headers, terminate output handlers etc.)
 *
 * @see https://phpfashion.com/jak-probiha-shutdown-v-php-a-volani-destruktoru
 *
 * @see https://man7.org/linux/man-pages/man7/signal.7.html
 * @see https://stackoverflow.com/questions/3333276/signal-handling-on-windows
 */
class ProcessInterceptor
{

    public const NAME = 'process';

    /** @var int */
    private static $interceptSignals = Intercept::NONE;

    /** @var int */
    private static $interceptAlarm = Intercept::NONE;

    /** @var int */
    private static $interceptExit = Intercept::NONE;

    /** @var int */
    private static $interceptShutdown = Intercept::NONE;

    /** @var int */
    private static $interceptAbort = Intercept::NONE;

    /**
     * Take control over pcntl_signal(), pcntl_async_signals() and sapi_windows_set_ctrl_handler()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptSignals(int $level = Intercept::LOG_CALLS): void
    {
        self::$interceptSignals = $level;
        Intercept::registerFunction(self::NAME, 'pcntl_signal', self::class);
        Intercept::registerFunction(self::NAME, 'pcntl_async_signals', self::class);
        Intercept::registerFunction(self::NAME, 'sapi_windows_set_ctrl_handler', self::class);
    }

    /**
     * Take control over pcntl_alarm()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptAlarm(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'pcntl_alarm', self::class);
        self::$interceptAlarm = $level;
    }

    /**
     * Takes control over exit() and die()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptExit(int $level = Intercept::LOG_CALLS): void
    {
        self::$interceptExit = $level;
        Intercept::registerFunction(self::NAME, 'exit', [self::class, 'fakeExit']);
        Intercept::registerFunction(self::NAME, 'die', [self::class, 'fakeExit']); // die() is just synonym of exit()
    }

    /**
     * Take control over ignore_user_abort()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptAbort(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'ignore_user_abort', self::class);
        self::$interceptAbort = $level;
    }

    /**
     * Takes control over register_shutdown_function()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptShutdown(int $level = Intercept::LOG_CALLS): void
    {
        self::$interceptShutdown = $level;
        Intercept::registerFunction(self::NAME, 'register_shutdown_function', self::class);
    }

    // decorators ------------------------------------------------------------------------------------------------------

    /**
     * @param callable|int $callable
     */
    public static function pcntl_signal(int $signal, $callable, bool $restartSysCalls = true): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSignals, __FUNCTION__, [$signal, $callable, $restartSysCalls], true);
    }

    public static function pcntl_async_signals(?bool $enable): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSignals, __FUNCTION__, [$enable], true);
    }

    public static function sapi_windows_set_ctrl_handler(callable $callable, bool $add): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSignals, __FUNCTION__, [$callable, $add], true);
    }

    public static function pcntl_alarm(int $seconds): int
    {
        return Intercept::handle(self::NAME, self::$interceptAlarm, __FUNCTION__, [$seconds], 0);
    }

    /**
     * @param string|int $status
     */
    public static function fakeExit($status = ''): void
    {
        Debugger::setTermination($status ? 'exit (' . $status . ')' : 'exit');

        if (self::$interceptExit & Intercept::SILENT) {
            exit($status);
        } elseif (self::$interceptExit & Intercept::LOG_CALLS) {
            Intercept::log(self::NAME, self::$interceptExit, 'exit', [$status], null);
            exit($status);
        } else {
            throw new LogicException('Not implemented.');
        }
    }

    /**
     * @param mixed ...$args
     */
    public static function register_shutdown_function(?callable $callback, ...$args): ?bool
    {
        return Intercept::handle(self::NAME, self::$interceptShutdown, __FUNCTION__, func_get_args(), null);
    }

    public static function ignore_user_abort(?bool $ignore): int
    {
        return Intercept::handle(self::NAME, self::$interceptAbort, __FUNCTION__, [$ignore], ignore_user_abort());
    }

}
