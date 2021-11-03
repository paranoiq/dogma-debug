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
use const E_ALL;
use const E_STRICT;
use function set_error_handler;

class RequestHandler
{

    /** @var bool Print request headers */
    public static $requestHeaders = false;

    /** @var bool Print request body */
    public static $requestBody = false;

    /** @var bool Print response headers */
    public static $responseHeaders = false;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var bool Controlling other exception handlers */
    private static $takeover = Takeover::NONE;

    /**
     * Take control over header()
     *
     * @param int $handler Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeover(int $handler): void
    {
        Takeover::register('header', [self::class, 'fakeHeader']);
        self::$takeover = $handler;
    }

    public static function fakeHeader(string $header, bool $replace = true, int $responseCode = 0): void
    {
        if (self::$takeover === Takeover::NONE) {
            header($header, $replace, $responseCode);
            return;
        } elseif (self::$takeover === Takeover::LOG_OTHERS) {
            header($header, $replace, $responseCode);
            $message = "User code setting error handler.";
        } elseif (self::$takeover === Takeover::PREVENT_OTHERS) {
            $message = "User code trying to set error handler (prevented).";
        } else {
            throw new LogicException('Not implemented.');
        }

        self::logTakeover($message);
    }

    private static function logTakeover(string $message): void
    {
        $message = Ansi::white(' ' . $message . ' ', Takeover::$labelColor);
        $callstack = Callstack::get()->filter(Dumper::$traceSkip);
        $trace = Dumper::formatCallstack($callstack, 1, 0, []);

        Debugger::send(Packet::TAKEOVER, $message, $trace);
    }

}