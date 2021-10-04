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
use function microtime;
use function restore_exception_handler;
use function set_exception_handler;

class ExceptionHandler
{

    /** @var int|null */
    public static $printLimit = 0;

    /** @var bool */
    public static $uniqueOnly = true;

    public static function enable(): void
    {
        set_exception_handler([self::class, 'handle']);
    }

    public static function disable(): void
    {
        restore_exception_handler();
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

    public static function handle(Throwable $e): void
    {
        DebugClient::init();

        self::log($e);
    }

    public static function log(Throwable $e): void
    {
        $time = microtime(true);
        $message = Ansi::white(' Exception: ', Ansi::LRED) . ' '
            . Dumper::name(get_class($e)) . ' ' . Ansi::lyellow($e->getMessage());

        $backtrace = $e->getTrace();
        $backtrace = Dumper::dumpBacktrace($backtrace, 0);

        DebugClient::send(Packet::ERROR, $message, $backtrace, $time);
    }

}
