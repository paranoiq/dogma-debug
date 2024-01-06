<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function error_reporting;
use const E_ALL;
use const E_STRICT;

/**
 * Tracks usage of error related functions
 */
class ErrorInterceptor
{

    public const NAME = 'error';

    /** @var array<array{string, string}> */
    public static $interceptExceptions = [
        ['Nette\Utils\Callback', 'invokeSafe'],
        ['Symfony\Component\Process\Pipes\UnixPipes', 'readAndWrite'],
        ['PhpAmqpLib\Wire\IO\AbstractIO', 'set_error_handler'],
    ];

    /** @var int */
    private static $interceptExceptionHandlers = Intercept::NONE;

    /** @var int */
    private static $interceptErrorHandlers = Intercept::NONE;

    /** @var int */
    private static $interceptErrorReporting = Intercept::NONE;

    /**
     * Take control over set_exception_handler() and restore_exception_handler()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptExceptionHandlers(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'set_exception_handler', self::class);
        Intercept::registerFunction(self::NAME, 'restore_exception_handler', self::class);
        self::$interceptExceptionHandlers = $level;
    }

    /**
     * Take control over set_error_handler() and restore_error_handler()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptErrorHandlers(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'set_error_handler', self::class);
        Intercept::registerFunction(self::NAME, 'restore_error_handler', self::class);
        self::$interceptErrorHandlers = $level;
    }

    /**
     * Take control over error_reporting()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptReporting(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'error_reporting', self::class);
        self::$interceptErrorReporting = $level;
    }

    // decorators ------------------------------------------------------------------------------------------------------

    public static function set_exception_handler(?callable $callback): ?callable
    {
        if ($callback !== null && Intercept::$wrapEventHandlers & Intercept::EVENT_EXCEPTION) {
            $callback = Intercept::wrapEventHandler($callback, Intercept::EVENT_EXCEPTION);
        }

        return Intercept::handle(self::NAME, self::$interceptExceptionHandlers, __FUNCTION__, [$callback], null);
    }

    public static function restore_exception_handler(): bool
    {
        return Intercept::handle(self::NAME, self::$interceptExceptionHandlers, __FUNCTION__, [], true);
    }

    public static function set_error_handler(?callable $callback, int $levels = E_ALL | E_STRICT): ?callable
    {
        if ($callback !== null && Intercept::$wrapEventHandlers & Intercept::EVENT_ERROR) {
            $callback = Intercept::wrapEventHandler($callback, Intercept::EVENT_ERROR);
        }

        return Intercept::handle(self::NAME, self::$interceptErrorHandlers, __FUNCTION__, [$callback, $levels], null, self::ignored());
    }

    public static function restore_error_handler(): bool
    {
        return Intercept::handle(self::NAME, self::$interceptErrorHandlers, __FUNCTION__, [], true, self::ignored());
    }

    public static function error_reporting(?int $level = null): int
    {
        $res = error_reporting();
        if ($level === null) {
            return error_reporting();
        }

        return Intercept::handle(self::NAME, self::$interceptErrorReporting, __FUNCTION__, [$level], $res, self::ignored());
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    private static function ignored(): bool
    {
        $frame = Callstack::get(Dumper::$traceFilters)->last();
        foreach (self::$interceptExceptions as $exception) {
            if ($frame->is($exception)) {
                return true;
            }
        }

        return false;
    }

}
