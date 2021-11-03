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

class Takeover
{

    public const NONE = 0; // allow other handlers to register
    public const LOG_OTHERS = 1; // log other handler attempts to register
    public const ALWAYS_LAST = 2; // always process events after other/native handlers
    public const ALWAYS_FIRST = 3; // always process events before other/native handlers
    public const PREVENT_OTHERS = 4; // do not pass events to other/native handlers

    /** @var bool Report files where code has been modified */
    public static $reportReplacements = true;

    /** @var string */
    public static $labelColor = Ansi::DMAGENTA;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var callable[] */
    private static $replacements = [];

    /**
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
            $pattern = "~(?<![a-zA-Z0-9_\\\\])\\\\?$function(\s*\()~i";
            $replacement = '\\' . $callable[0] . '::' . $callable[1] . '$1';

            $result = preg_replace($pattern, $replacement, $code);

            if ($result !== $code) {
                $replaced[] = $function;
            }

            $code = $result;
        }

        if (self::$reportReplacements && $replaced !== []) {
            $functions = implode(', ', $replaced);
            Debugger::send(Packet::TAKEOVER, Ansi::white(" Replaced $functions in $file ", Ansi::DMAGENTA));
        }

        return $code;
    }

}