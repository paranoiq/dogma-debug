<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function preg_replace;

/**
 * Using FileHandler and PharHandler, this class rewrites loaded PHP code on-the-fly to take control of various
 * debugging related functions (like register_error_handler etc.). This is to prevent other tools from changing
 * the environment and potentially silence some errors or just to track which tools are doing it (test frameworks etc.)
 *
 * FileHandler (and PharHandler if you are debugging a packed application) must be enabled for this
 *
 * Supported handlers so far and what functions they overload:
 * - ErrorHandler: set_error_handler(), restore_error_handler(), error_reporting()
 * - ExceptionHandler: set_exception_handler(), restore_exception_handler()
 * - ShutdownHandler: register_shutdown_function()
 * - RequestHandler: header()
 */
class Takeover
{

    public const NONE = 0; // allow other handlers to register
    public const LOG_OTHERS = 1; // log other handler attempts to register
    public const ALWAYS_LAST = 2; // always process events after other/native handlers
    public const ALWAYS_FIRST = 3; // always process events before other/native handlers
    public const PREVENT_OTHERS = 4; // do not pass events to other/native handlers

    /** @var bool Report files where code has been modified */
    public static $logReplacements = true;

    /** @var bool Report when app code tries to call overloaded functions */
    public static $logAttempts = true;

    /** @var bool */
    public static $filterTrace = true;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var array<string, array{class-string, string}> */
    private static $replacements = [];

    /**
     * Register system function to be overloaded with a static call
     * @param array{class-string, string} $callable
     */
    public static function register(string $function, array $callable): void
    {
        self::$replacements[$function] = $callable;
    }

    public static function enabled(): bool
    {
        return self::$replacements !== [];
    }

    public static function hack(string $code, string $file): string
    {
        $replaced = [];
        foreach (self::$replacements as $function => $callable) {
            // must not be preceded by: other name characters, namespace, `->`, `$` or `function `
            $pattern = "~(?<![a-zA-Z0-9_\\\\>$])(?<!function )\\\\?$function(\s*\()~i";
            $replacement = '\\' . $callable[0] . '::' . $callable[1] . '$1';

            $result = preg_replace($pattern, $replacement, $code);

            if ($result !== $code) {
                $replaced[] = $function . '()';
            }

            $code = $result;
        }

        if (self::$logReplacements && $replaced !== []) {
            $functions = Str::join($replaced, ', ', ' and ');
            $message = Ansi::lmagenta("Overloaded $functions in: ") . Dumper::file($file);
            Debugger::send(Packet::TAKEOVER, $message);
        }

        return $code;
    }

    public static function log(string $message): void
    {
        if (!self::$logAttempts) {
            return;
        }

        $message = Ansi::white(' ' . $message . ' ', Ansi::DMAGENTA);
        $callstack = Callstack::get();
        if (self::$filterTrace) {
            $callstack = $callstack->filter(Dumper::$traceSkip);
        }
        $trace = Dumper::formatCallstack($callstack, 1, 0, []);

        Debugger::send(Packet::TAKEOVER, $message, $trace);
    }

}
