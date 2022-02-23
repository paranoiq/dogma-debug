<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Throwable;
use function get_class;
use function is_a;
use function restore_exception_handler;
use function set_exception_handler;

/**
 * Catches and displays exceptions
 */
class ExceptionHandler
{

    public const NAME = 'exception';

    /** @var int */
    public static $traceLength = 1000;

    /** @var int */
    public static $traceArgsDepth = 1;

    /** @var int */
    public static $traceCodeLines = 5;

    /** @var int */
    public static $traceCodeDepth = 5;

    /** @var class-string[] */
    public static $logExceptions = [];

    /** @var class-string[] */
    public static $notLogExceptions = [];

    /** @var bool */
    public static $filterTrace = true;

    /** @var bool */
    private static $enabled = false;

    public static function enable(): void
    {
        set_exception_handler([self::class, 'handle']);
        self::$enabled = true;
    }

    public static function disable(): void
    {
        restore_exception_handler();
        self::$enabled = false;
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function handle(Throwable $e): void
    {
        Debugger::init();

        self::logFatal($e);

        Debugger::setTermination('exception');

        exit(1);
    }

    public static function logFatal(Throwable $e): void
    {
        $message = Ansi::white(' Exception: ', Ansi::LRED) . ' '
            . Dumper::name(get_class($e)) . ' ' . Ansi::lyellow($e->getMessage());

        // so io operations will work after PHP shutting down user handlers
        FileStreamWrapper::disable();
        PharStreamWrapper::disable();
        HttpStreamWrapper::disable();
        FtpStreamWrapper::disable();

        try {
            $callstack = Callstack::fromThrowable($e);
            if (self::$filterTrace) {
                $callstack = $callstack->filter(Dumper::$traceFilters);
            }
            $trace = Dumper::formatCallstack($callstack, 1000, 1, 5, 1);
        } catch (Throwable $e) {
            Debugger::dump($e);

            return;
        }

        Debugger::send(Packet::EXCEPTION, $message, $trace);
    }

    /**
     * @param class-string[] $log
     * @param class-string[] $notLog
     */
    public static function inspectThrownExceptions(array $log = [], array $notLog = []): void
    {
        self::$logExceptions = $log;
        self::$notLogExceptions = $notLog;

        Intercept::inspectCaughtExceptions(self::NAME, self::class, 'log');
    }

    public static function log(Throwable $e): void
    {
        $log = true;
        if (self::$logExceptions !== []) {
            $log = false;
            foreach (self::$logExceptions as $exceptionClass) {
                if (is_a($e, $exceptionClass)) {
                    $log = true;
                }
            }
        }
        if (self::$notLogExceptions !== []) {
            foreach (self::$notLogExceptions as $exceptionClass) {
                if (is_a($e, $exceptionClass)) {
                    $log = false;
                }
            }
        }
        if (!$log) {
            return;
        }

        $message = Ansi::white(' Exception: ', Ansi::LPURPLE) . ' '
            . Dumper::name(get_class($e)) . ' ' . Ansi::lyellow($e->getMessage());

        try {
            $properties = (array) $e;
            foreach ($properties as $name => $value) {
                if (in_array($name, ["\0Exception\0string", "\0Exception\0previous", "\0Exception\0trace", "\0*\0file", "\0*\0line", "\0*\0message", "xdebug_message"], true)) {
                    unset($properties[$name]);
                }
                if ($name === "\0*\0code" && $value === 0) {
                    unset($properties[$name]);
                }
            }
            if ($properties !== []) {
                $message .= ' ' . Dumper::bracket('{') . "\n" . Dumper::dumpProperties($properties, 1, get_class($e)) . "\n" . Dumper::bracket('}');
            }

            $callstack = Callstack::fromThrowable($e);
            if (self::$filterTrace) {
                $callstack = $callstack->filter(Dumper::$traceFilters);
            }
            $trace = Dumper::formatCallstack($callstack, 1000, 1, [5, 5, 5, 5, 5]);
        } catch (Throwable $e) {
            Debugger::dump($e);

            return;
        }

        Debugger::send(Packet::EXCEPTION, $message, $trace);
    }

}
