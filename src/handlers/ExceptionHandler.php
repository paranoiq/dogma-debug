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
use function in_array;
use function is_a;
use function restore_exception_handler;
use function set_exception_handler;
use function str_repeat;

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
    public static $traceCodeDepth = 1;

    /** @var class-string[] */
    public static $logExceptions = [];

    /** @var class-string[] */
    public static $notLogExceptions = [];

    /** @var bool */
    public static $filterTrace = true;

    /** @var bool */
    private static $enabled = false;

    /**
     * @param class-string[] $log
     * @param class-string[] $notLog
     */
    public static function inspectThrownExceptions(array $log = [], array $notLog = []): void
    {
        self::$logExceptions = $log;
        self::$notLogExceptions = $notLog;

        Intercept::inspectCaughtExceptions(self::NAME, [self::class, 'log']);
    }

    public static function enable(): void
    {
        set_exception_handler([self::class, 'handle']);
        self::$enabled = true;

        if (Debugger::$reserved === null) {
            Debugger::$reserved = str_repeat('!', Debugger::$reserveMemory);
        }
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
        Debugger::$reserved = false;
        Debugger::init();

        self::logFatal($e);

        Debugger::setTermination('exception');

        exit(1);
    }

    public static function logFatal(Throwable $exception): void
    {
        // so io operations will work after PHP shutting down user handlers
        FileStreamWrapper::disable();
        PharStreamWrapper::disable();
        HttpStreamWrapper::disable();
        FtpStreamWrapper::disable();

        $message = self::formatException($exception);

        Debugger::send(Packet::EXCEPTION, $message);
    }

    public static function log(Throwable $exception): void
    {
        $log = true;
        if (self::$logExceptions !== []) {
            $log = false;
            foreach (self::$logExceptions as $exceptionClass) {
                if (is_a($exception, $exceptionClass)) {
                    $log = true;
                }
            }
        }
        if (self::$notLogExceptions !== []) {
            foreach (self::$notLogExceptions as $exceptionClass) {
                if (is_a($exception, $exceptionClass)) {
                    $log = false;
                }
            }
        }
        if (!$log) {
            return;
        }

        $message = self::formatException($exception);

        Debugger::send(Packet::EXCEPTION, $message);
    }

    public static function formatException(Throwable $exception): string
    {
        static $filteredProperties = [
            "\0Exception\0string",
            "\0Exception\0trace",
            "\0Exception\0previous",
            "\0Error\0string",
            "\0Error\0trace",
            "\0Error\0previous",
            "\0*\0file",
            "\0*\0line",
            "\0*\0message",
            "xdebug_message",
        ];

        $first = true;
        $message = '';

        while ($exception !== null) {
            $message .= $first
                ? Ansi::white(' Exception: ', Ansi::LRED)
                : "\n" . Ansi::white(' Previous: ', Ansi::LRED);
            $message .= ' ' . Dumper::class(get_class($exception)) . ' ' . Ansi::lyellow($exception->getMessage());

            try {
                $properties = (array) $exception;
                foreach ($properties as $name => $value) {
                    if (in_array($name, $filteredProperties, true)) {
                        unset($properties[$name]);
                    }
                    if ($name === "\0*\0code" && $value === 0) {
                        unset($properties[$name]);
                    }
                }
                if ($properties !== []) {
                    Dumper::$maxDepth = 3;
                    $message .= ' ' . Dumper::bracket('{') . "\n" . Dumper::dumpProperties($properties, 0, get_class($exception)) . "\n" . Dumper::bracket('}');
                }

                $callstack = Callstack::fromThrowable($exception);
                if (self::$filterTrace) {
                    $callstack = $callstack->filter(Dumper::$traceFilters);
                }
                $message .= "\n" . Dumper::formatCallstack($callstack, self::$traceLength, self::$traceArgsDepth, self::$traceCodeLines, self::$traceCodeDepth);
            } catch (Throwable $exception) {
                Debugger::label('Exception formatting failed with:', null, 'r');
                Debugger::dump($exception, 4);
            }

            $first = false;
            $exception = $exception->getPrevious();
        }

        return $message;
    }

}
