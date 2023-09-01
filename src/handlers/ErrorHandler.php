<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Throwable;
use function error_reporting;
use function ksort;
use function ob_start;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use const E_ALL;
use const E_COMPILE_ERROR;
use const E_COMPILE_WARNING;
use const E_CORE_ERROR;
use const E_CORE_WARNING;
use const E_DEPRECATED;
use const E_ERROR;
use const E_NOTICE;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;
use const E_STRICT;
use const E_USER_DEPRECATED;
use const E_USER_ERROR;
use const E_USER_NOTICE;
use const E_USER_WARNING;
use const E_WARNING;

/**
 * Tracks and displays errors, warnings and notices
 */
class ErrorHandler
{

    public const NAME = 'error';

    // will log uncatchable errors like "Out of memory" or parse errors. these are not passed to error handler
    // even with E_ALL, so we are using output buffer to read error messages from the PHP output
    public const E_UNCATCHABLE_ERROR = 1 << 29;

    /** @var bool Prevent error from bubbling to other error handlers (including the native handler) */
    public static $catch = false;

    /** @var int Max count of errors printed to output */
    public static $printLimit = 0;

    /** @var bool Print only unique error types and origins */
    public static $uniqueOnly = true;

    /** @var bool List errors on end of request */
    public static $listErrors = true;

    /** @var bool Show last error which could have been hidden by another error handler */
    public static $showLastError = true;

    /** @var bool Count errors muted with @ */
    public static $countMutedErrors = false;

    /** @var bool Show errors muted with @ */
    public static $showMutedErrors = false;

    /** @var string[][] (string $typeAndMessage => string[] $fileAndLine) */
    public static $ignore = [];

    /** @var bool */
    public static $filterTrace = true;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var int */
    private static $printCount = 0;

    /** @var int */
    private static $ignoreCount = 0;

    /** @var int */
    private static $count = 0;

    /** @var array<int, int> */
    private static $types = [];

    /** @var array<string, array<string, int>> */
    private static $messages = [];

    /** @var callable|null */
    private static $previous;

    /** @var bool */
    private static $enabled = false;

    public static function enable(int $types = E_ALL, bool $catch = false, int $printLimit = 0, bool $uniqueOnly = true): void
    {
        self::$catch = $catch;
        self::$printLimit = $printLimit;
        self::$uniqueOnly = $uniqueOnly;

        self::$previous = set_error_handler([self::class, 'handleError'], $types);

        if ($types & self::E_UNCATCHABLE_ERROR) {
            ob_start([self::class, 'handleOutput'], 1);
        }

        self::$enabled = true;

        if (Debugger::$reserved === null) {
            Debugger::$reserved = str_repeat('!', Debugger::$reserveMemory);
        }
    }

    public static function disable(): void
    {
        restore_error_handler();
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function handleError(
        int $type,
        string $message,
        ?string $file = null,
        ?int $line = null
    ): bool
    {
        if ($file !== null) {
            $file = str_replace('\\', '/', $file);
        }

        try {
            Debugger::init();
            self::logCounts($type, $message, $file, $line);

            if (self::$catch) {
                return true;
            } elseif (self::$previous !== null) {
                return (bool) (self::$previous)($type, $message, $file, $line, null);
            } else {
                return false;
            }
        } catch (Throwable $e) {
            $trace = Dumper::info('^--- ') . Dumper::fileLine($e->getFile(), $e->getLine());
            Debugger::send(Message::ERROR, $e->getMessage(), $trace);
            return false;
        }
    }

    public static function handleOutput(string $output): bool
    {
        if (str_contains($output, 'Fatal error:')) {
            if (str_contains($output, 'Allowed memory size of')) {
                // ending buffering here only produces a notice, so no `ob_end_...()` here

                // free reserved memory
                Debugger::$reserved = false;

                preg_match('~Allowed memory size of ([0-9]+) bytes exhausted \\(tried to allocate ([0-9]+) bytes\\)~', $output, $m);
                $message = Ansi::white(' ' . self::typeDescription(self::E_UNCATCHABLE_ERROR) . ': ', Ansi::LRED) . ' ' . Ansi::lyellow($m[0]);

                $callstack = Callstack::fromOutOfMemoryMessage($output);
                $backtrace = Dumper::formatCallstack($callstack, 1000, null, 5, 1);

                Debugger::send(Message::ERROR, $message, $backtrace);
                Debugger::setTermination('memory limit (' . Units::memory(Resources::memoryLimit()) . ')');
            }// else {
                // todo: ???
            //}
        }

        return false;
    }

    private static function logCounts(int $type, string $message, ?string $file = null, ?int $line = null): void
    {
        // todo: filter better (spams logs when $count/showMutedErrors is on and is useless)
        if (str_starts_with($message, 'stat(): stat failed for')) {
            return;
        }

        $muted = (error_reporting() & $type) === 0;
        if ($muted && !self::$countMutedErrors) {
            return;
        }

        self::$count++;
        if (!isset(self::$types[$type])) {
            self::$types[$type] = 0;
        }
        self::$types[$type]++;

        $typeMessage = self::typeDescription($type) . ': ' . $message;
        $fileLine = $file . ':' . $line;

        // complete match (faster)
        $places = self::$ignore[$typeMessage] ?? [];
        // start match
        if ($places === []) {
            foreach (self::$ignore as $m => $p) {
                if (str_starts_with($m, '~')) {
                    if (preg_match($m, $typeMessage)) {
                        $places = $p;
                    }
                } else {
                    if (str_starts_with($typeMessage, $m)) {
                        $places = $p;
                    }
                }
            }
        }
        foreach ($places as $place) {
            // place match
            if (str_contains($fileLine, $place)) {
                self::$ignoreCount++;
                return;
            }
        }

        if ($muted) {
            $typeMessage = '[muted] ' . $typeMessage;
        }
        if (!isset(self::$messages[$typeMessage][$fileLine])) {
            self::$messages[$typeMessage][$fileLine] = 0;
        }
        self::$messages[$typeMessage][$fileLine]++;

        if (self::$printLimit !== null && self::$printCount >= self::$printLimit) {
            return;
        }
        // todo: wtf?
        //if (self::$uniqueOnly && self::$messages[$typeMessage][$fileLine] < 2) {
        //    return;
        //}
        if ($muted && !self::$showMutedErrors) {
            return;
        }

        self::$printCount++;
        self::log($type, $message);
    }

    public static function log(int $type, string $message): void
    {
        $message = Ansi::white(' ' . self::typeDescription($type) . ': ', Ansi::LRED) . ' ' . Ansi::lyellow($message);

        $callstack = Callstack::get(Dumper::$traceFilters, self::$filterTrace);
        $backtrace = Dumper::formatCallstack($callstack, 1000, null, 5, 1);

        Debugger::send(Message::ERROR, $message, $backtrace);
    }

    private static function typeDescription(int $type): string
    {
        static $types = [
            self::E_UNCATCHABLE_ERROR => 'Fatal error',
            E_ERROR => 'Fatal Error',
            E_USER_ERROR => 'User Error',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_CORE_ERROR => 'Core Error',
            E_COMPILE_ERROR => 'Compile Error',
            E_PARSE => 'Parse Error',
            E_WARNING => 'Warning',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_WARNING => 'User Warning',
            E_NOTICE => 'Notice',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict standards',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
        ];

        return $types[$type] ?? 'Unknown error';
    }

    // stats -----------------------------------------------------------------------------------------------------------

    public static function getCount(): int
    {
        return self::$count;
    }

    /**
     * @return array<int, int>
     */
    public static function getTypes(): array
    {
        ksort(self::$types);

        return self::$types;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public static function getMessages(): array
    {
        ksort(self::$messages);

        return self::$messages;
    }

}
