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
use Throwable;
use function get_class;
use function restore_exception_handler;
use function set_exception_handler;

class ExceptionHandler
{

    /** @var bool */
    public static $filterTrace = true;

    /** @var bool Controlling other exception handlers */
    private static $takeover = Takeover::NONE;

    /** @var bool */
    private static $enabled = false;

    public static function enable(): void
    {
        set_exception_handler([self::class, 'handle']);
        self::$enabled = true;
    }

    /**
     * Take control over set_exception_handler() and restore_exception_handler()
     *
     * @param int $handler Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeover(int $handler): void
    {
        Takeover::register('set_exception_handler', [self::class, 'fakeRegister']);
        Takeover::register('restore_exception_handler', [self::class, 'fakeRestore']);
        self::$takeover = $handler;
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

        self::log($e);
    }

    public static function fakeRegister(?callable $callback): ?callable
    {
        if (self::$takeover === Takeover::NONE) {
            return set_exception_handler($callback);
        } elseif (self::$takeover === Takeover::LOG_OTHERS) {
            $old = set_exception_handler($callback);
            $message = "User code setting exception handler.";
        } elseif (self::$takeover === Takeover::PREVENT_OTHERS) {
            $old = null;
            $message = "User code trying to set exception handler (prevented).";
        } else {
            throw new LogicException('Not implemented.');
        }

        self::logTakeover($message);

        return $old;
    }

    public static function fakeRestore(): bool
    {
        if (self::$takeover === Takeover::NONE) {
            return restore_exception_handler();
        } elseif (self::$takeover === Takeover::LOG_OTHERS) {
            restore_exception_handler();
            $message = "User code restoring previous exception handler.";
        } elseif (self::$takeover === Takeover::PREVENT_OTHERS) {
            $message = "User code trying to restore previous exception handler (prevented).";
        } else {
            throw new LogicException('Not implemented.');
        }

        self::logTakeover($message);

        return true;
    }

    private static function logTakeover(string $message): void
    {
        $message = Ansi::white(' ' . $message . ' ', Takeover::$labelColor);
        $callstack = Callstack::get();
        if (self::$filterTrace) {
            $callstack = $callstack->filter(Dumper::$traceSkip);
        }
        $trace = Dumper::formatCallstack($callstack, 1, 0, []);

        Debugger::send(Packet::TAKEOVER, $message, $trace);
    }

    public static function log(Throwable $e): void
    {
        $message = Ansi::white(' Exception: ', Ansi::LRED) . ' '
            . Dumper::name(get_class($e)) . ' ' . Ansi::lyellow($e->getMessage());

        FileHandler::disable();
        PharHandler::disable();

        try {
            $callstack = Callstack::fromThrowable($e);
            if (self::$filterTrace) {
                $callstack = $callstack->filter(Dumper::$traceSkip);
            }
            $trace = Dumper::formatCallstack($callstack, 1000, 1, [5, 5, 5, 5, 5]);
        } catch (Throwable $e) {
            rd($e);
        }

        Debugger::send(Packet::EXCEPTION, $message, $trace);
    }

}
