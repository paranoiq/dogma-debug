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
use function debug_backtrace;
use function get_class;
use function restore_exception_handler;
use function set_exception_handler;

class ExceptionHandler
{

    public static function enable(): void
    {
        set_exception_handler([self::class, 'handle']);
    }

    public static function disable(): void
    {
        restore_exception_handler();
    }

    public static function handle(Throwable $e): void
    {
        DebugClient::init();

        self::log($e);
    }

    public static function log(Throwable $e): void
    {
        $message = Ansi::white(' Exception: ', Ansi::LRED) . ' '
            . Dumper::name(get_class($e)) . ' ' . Ansi::lyellow($e->getMessage());

        $trace = $e->getTrace() ?: debug_backtrace();
        $callstack = Callstack::fromBacktrace($trace)->filter(Dumper::$traceSkip);
        $trace = Dumper::formatCallstack($callstack, 1000, 1, [5, 5, 5, 5, 5]);

        DebugClient::send(Packet::EXCEPTION, $message, $trace);
    }

}
