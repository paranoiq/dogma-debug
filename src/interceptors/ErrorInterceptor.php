<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function array_pop;
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

    /** @var bool */
    public static $trackErrorHandlers = false;

    /** @var bool */
    public static $trackExceptionHandlers = false;

    /** @var int */
    private static $interceptExceptionHandlers = Intercept::NONE;

    /** @var int */
    private static $interceptErrorHandlers = Intercept::NONE;

    /** @var list<array{callable, Callstack}> */
    private static $errorHandlers = [];

    /** @var list<array{callable, Callstack}> */
    private static $exceptionHandlers = [];

    /**
     * Take control over set_exception_handler() and restore_exception_handler()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptExceptionHandlers(int $level = Intercept::LOG_CALLS): void
    {
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for set_exception_handler and restore_exception_handler.");
        }

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
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for set_error_handler and restore_error_handler.");
        }

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
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for error_reporting.");
        }

        Intercept::registerFunction(self::NAME, 'error_reporting', self::class);
        self::$interceptErrorReporting = $level;
    }

    public static function dumpErrorHandlers(): void
    {
        Debugger::label('Error handlers:');
        foreach (self::$errorHandlers as $i => [$callback, $callstack]) {
            Debugger::dump($callback, null, null, $i);
            Debugger::callstack(10, 0, 0, 0, $callstack);
        }
    }

    public static function dumpExceptionHandlers(): void
    {
        Debugger::label('Exception handlers:');
        foreach (self::$exceptionHandlers as $i => [$callback, $callstack]) {
            Debugger::dump($callback, null, null, $i);
            Debugger::callstack(10, 0, 0, 0, $callstack);
        }
    }

    // decorators ------------------------------------------------------------------------------------------------------

    public static function set_exception_handler(?callable $callback): ?callable
    {
        if ($callback !== null && Intercept::$wrapEventHandlers & Intercept::EVENT_EXCEPTION) {
            $callback = Intercept::wrapEventHandler($callback, Intercept::EVENT_EXCEPTION);
        }

        $result = Intercept::handle(self::NAME, self::$interceptExceptionHandlers, __FUNCTION__, [$callback], null);
        if (self::$trackExceptionHandlers) {
            $callstack = Callstack::get();
            self::$exceptionHandlers[] = [$callback, $callstack];
        }

        return $result;
    }

    public static function restore_exception_handler(): bool
    {
        $result = Intercept::handle(self::NAME, self::$interceptExceptionHandlers, __FUNCTION__, [], true);
        if ($result && self::$trackExceptionHandlers) {
            array_pop(self::$exceptionHandlers);
        }

        return $result;
    }

    /**
     * @param callable|null $callback
     */
    public static function set_error_handler($callback, int $levels = E_ALL | E_STRICT): ?callable
    {
        if ($callback !== null && Intercept::$wrapEventHandlers & Intercept::EVENT_ERROR) {
            $callback = Intercept::wrapEventHandler($callback, Intercept::EVENT_ERROR);
        }

        $result = Intercept::handle(self::NAME, self::$interceptErrorHandlers, __FUNCTION__, [$callback, $levels], null, self::ignored());
        if (self::$trackErrorHandlers) {
            $callstack = Callstack::get();
            self::$errorHandlers[] = [$callback, $callstack];
        }

        return $result;
    }

    public static function restore_error_handler(): bool
    {
        $result = Intercept::handle(self::NAME, self::$interceptErrorHandlers, __FUNCTION__, [], true, self::ignored());
        if ($result && self::$trackErrorHandlers) {
            array_pop(self::$errorHandlers);
        }

        return $result;
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
        $frame = Callstack::get(Dumper::$config->traceFilters)->last();
        foreach (self::$interceptExceptions as $exception) {
            if ($frame->is($exception)) {
                return true;
            }
        }

        return false;
    }

}
