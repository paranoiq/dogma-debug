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
use function register_shutdown_function;

class ShutdownHandler
{

    /** @var bool */
    public static $filterTrace = true;

    /** @var bool Controlling other exception handlers */
    private static $takeover = Takeover::NONE;

    /**
     * Take control over register_shutdown_function()
     *
     * @param int $handler Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeover(int $handler): void
    {
        Takeover::register('register_shutdown_function', [self::class, 'fakeRegister']);
        self::$takeover = $handler;
    }

    public static function fakeRegister(?callable $callback, ...$args): ?bool
    {
        if (self::$takeover === Takeover::NONE) {
            return register_shutdown_function($callback, ...$args);
        } elseif (self::$takeover === Takeover::LOG_OTHERS) {
            $old = register_shutdown_function($callback, ...$args);
            $message = "User code setting shutdown handler.";
        } elseif (self::$takeover === Takeover::PREVENT_OTHERS) {
            $old = null;
            $message = "User code trying to set shutdown handler (prevented).";
        } else {
            throw new LogicException('Not implemented.');
        }

        self::logTakeover($message);

        return $old;
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

}
