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
use function error_reporting;
use function microtime;
use function restore_error_handler;
use function set_error_handler;
use function strpos;
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

class ErrorHandler
{

    /** @var bool */
    public static $catch = false;

    /** @var int|null */
    public static $printLimit = 0;

    /** @var bool */
    public static $uniqueOnly = true;

    /** @var string[][] (string $typeAndMessage => string[] $fileAndLine) */
    public static $ignore = [];

    /** @var int */
    private static $printCount = 0;

    /** @var int */
    private static $ignoreCount = 0;

    /** @var int */
    private static $count = 0;

    /** @var array<int, int> */
    private static $types = [];

    /** @var array<string, int> */
    private static $messages = [];

    /** @var callable|null */
    private static $previous;

    public static function enable(int $types = E_ALL): void
    {
        self::$previous = set_error_handler([self::class, 'handle'], $types);
    }

    public static function disable(): void
    {
        restore_error_handler();
    }

    /** alias for enable() */
    public static function register(): void
    {
        self::enable();
    }

    /** alias for disable() */
    public static function unregister(): void
    {
        self::disable();
    }

    public static function handle(
        int $type,
        string $message,
        ?string $file = null,
        ?int $line = null
    ): bool
    {
        try {
            DebugClient::init();
            self::logCounts($type, $message, $file, $line);

            if (self::$catch) {
                return true;
            } elseif (self::$previous !== null) {
                return (bool)(self::$previous)($type, $message, $file, $line, null);
            } else {
                return false;
            }
        } catch (Throwable $e) {
            $trace = Dumper::info('^--- ') . Dumper::fileLine($e->getFile(), $e->getLine());
            DebugClient::send(Packet::ERROR, $e->getMessage(), $trace);
            return false;
        }
    }

    private static function logCounts(int $type, string $message, ?string $file = null, ?int $line = null): void
    {
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
            foreach (self::$ignore as $tm => $pl) {
                if (strpos($typeMessage, $tm) === 0) {
                    $places = $pl;
                }
            }
        }
        foreach ($places as $place) {
            // place match
            if (strpos($fileLine, $place) !== false) {
                self::$ignoreCount++;
                return;
            }
        }

        if (!isset(self::$messages[$typeMessage][$fileLine])) {
            self::$messages[$typeMessage][$fileLine] = 0;
        }
        self::$messages[$typeMessage][$fileLine]++;

        if (
            (self::$printLimit === null || self::$printCount < self::$printLimit)
            && (!self::$uniqueOnly || self::$messages[$typeMessage][$fileLine] < 2)
            && (error_reporting() & $type) !== 0
        ) {
            self::$printCount++;
            self::log($type, $message, $file, $line);
        }
    }

    public static function log(int $type, string $message, ?string $file = null, ?int $line = null): void
    {
        $time = microtime(true);
        $message = Ansi::white(' ' . self::typeDescription($type) . ': ', Ansi::LRED) . ' ' . Ansi::lyellow($message);

        $backtrace = Dumper::formatCallstack(Callstack::get()->filter(Dumper::$traceSkip), 1000, null, [5]);

        DebugClient::send(Packet::ERROR, $message, $backtrace, $time);
    }

    private static function typeDescription(int $type): string
    {
        static $types = [
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
     * @return array<string, int>
     */
    public static function getMessages(): array
    {
        ksort(self::$messages);

        return self::$messages;
    }

}
