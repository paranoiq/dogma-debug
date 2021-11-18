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
use function error_reporting;
use function microtime;
use function restore_error_handler;
use function set_error_handler;

/**
 * Tracks and displays error, warnings and notices
 *
 * Error handling flow:
 * There are two modes in which this error handler can operate:
 *
 * 1) normal "civilized" behavior (default)
 * - in this case error handler registers as usual and receives a previously defined handler (that may be the native error handler)
 * - after handling an error you may decide, whether to call the previously registered error handler via $catch property
 *
 * 2) taking control over the whole error handling queue (assisted by File/PharHandler rewriting loaded code)
 * - in this case functionality regarding previously registered handler remains unchanged, but
 * - you can configure behaviour regarding handlers registered after this one, which should, under normal circumstances,
 *  take precedence and be called first (and maybe catching the errors and not passing them to other handlers)
 * - by setting $takeover property to one of TAKEOVER_* constants, you can enforce this handler to be always called,
 *  and if needed, change the order in which it is called or even suppress other error handlers entirely
 */
class ErrorHandler
{

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

    /** @var array<array{string, string}> */
    public static $takeoverExceptions = [
        ['Nette\Utils\Callback', 'invokeSafe'],
        ['Symfony\Component\Process\Pipes\UnixPipes', 'readAndWrite'],
        ['PhpAmqpLib\Wire\IO\AbstractIO', 'set_error_handler'],
    ];

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

    /** @var int */
    private static $takeoverHandlers = Takeover::NONE;

    /** @var int */
    private static $takeoverDisplay = Takeover::NONE;

    public static function enable(int $types = E_ALL, bool $catch = false, int $printLimit = 0, bool $uniqueOnly = true): void
    {
        self::$catch = $catch;
        self::$printLimit = $printLimit;
        self::$uniqueOnly = $uniqueOnly;

        self::$previous = set_error_handler([self::class, 'handle'], $types);
        self::$enabled = true;
    }

    public static function disable(): void
    {
        restore_error_handler();
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function handle(
        int $type,
        string $message,
        ?string $file = null,
        ?int $line = null
    ): bool
    {
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
            Debugger::send(Packet::ERROR, $e->getMessage(), $trace);
            return false;
        }
    }

    private static function logCounts(int $type, string $message, ?string $file = null, ?int $line = null): void
    {
        // todo: filter better (spams logs when $count/showMutedErrors is on and is useless)
        if (Str::startsWith($message, 'stat(): stat failed for')) {
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
                if (Str::startsWith($typeMessage, $m)) {
                    $places = $p;
                }
            }
        }
        foreach ($places as $place) {
            // place match
            if (Str::contains($fileLine, $place)) {
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
        if (self::$uniqueOnly && self::$messages[$typeMessage][$fileLine] < 2) {
            return;
        }
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
        $backtrace = Dumper::formatCallstack($callstack, 1000, null, [5]);

        Debugger::send(Packet::ERROR, $message, $backtrace);
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
     * @return array<string, array<string, int>>
     */
    public static function getMessages(): array
    {
        ksort(self::$messages);

        return self::$messages;
    }

    // takeover handlers -----------------------------------------------------------------------------------------------

    /**
     * Take control over set_error_handler() and restore_error_handler()
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::ALWAYS_FIRST|Takeover::ALWAYS_LAST|Takeover::PREVENT_OTHERS
     */
    public static function takeoverHandlers(int $level = Takeover::LOG_OTHERS): void
    {
        Takeover::register('error', 'set_error_handler', [self::class, 'fakeRegister']);
        Takeover::register('error', 'restore_error_handler', [self::class, 'fakeRestore']);
        self::$takeoverHandlers = $level;
    }

    /**
     * Take control over error_reporting() and display_errors()
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeoverDisplay(int $level = Takeover::LOG_OTHERS): void
    {
        Takeover::register('error', 'error_reporting', [self::class, 'fakeReporting']);
        Takeover::register('error', 'display_errors', [self::class, 'fakeDisplay']);
        self::$takeoverDisplay = $level;
    }

    public static function fakeReporting(?int $level = null): int
    {
        if ($level === null) {
            return error_reporting();
        } else {
            return Takeover::handle('error', self::$takeoverHandlers, 'error_reporting', [$level], null, self::allowed());
        }
    }

    public static function fakeRegister(?callable $callback, int $levels = E_ALL | E_STRICT): ?callable
    {
        return Takeover::handle('error', self::$takeoverHandlers, 'set_error_handler', [$callback, $levels], null, self::allowed());
    }

    public static function fakeRestore(): bool
    {
        return Takeover::handle('error', self::$takeoverHandlers, 'restore_error_handler', [], null, self::allowed());
    }

    private static function allowed(): bool
    {
        $frame = Callstack::get(Dumper::$traceFilters)->last();
        foreach (self::$takeoverExceptions as $exception) {
            if ($frame->is($exception)) {
                return true;
            }
        }

        return false;
    }

}
