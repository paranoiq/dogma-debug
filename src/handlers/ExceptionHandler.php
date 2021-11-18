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
use function restore_exception_handler;
use function set_exception_handler;

/**
 * Catches and displays exceptions
 */
class ExceptionHandler
{

    /** @var bool */
    public static $filterTrace = true;

    /** @var int Controlling other exception handlers */
    private static $takeoverHandlers = Takeover::NONE;

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

        self::log($e);
    }

    public static function log(Throwable $e): void
    {
        $message = Ansi::white(' Exception: ', Ansi::LRED) . ' '
            . Dumper::name(get_class($e)) . ' ' . Ansi::lyellow($e->getMessage());

        FileStreamHandler::disable();
        PharStreamHandler::disable();

        try {
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

    // takeover handlers -----------------------------------------------------------------------------------------------

    /**
     * Take control over set_exception_handler() and restore_exception_handler()
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeoverHandlers(int $level): void
    {
        Takeover::register('exception', 'set_exception_handler', [self::class, 'fakeRegister']);
        Takeover::register('exception', 'restore_exception_handler', [self::class, 'fakeRestore']);
        self::$takeoverHandlers = $level;
    }

    public static function fakeRegister(?callable $callback): ?callable
    {
        return Takeover::handle('exception', self::$takeoverHandlers, 'set_exception_handler', [$callback], null);
    }

    public static function fakeRestore(): bool
    {
        return Takeover::handle('exception', self::$takeoverHandlers, 'restore_exception_handler', [], null);
    }

}
