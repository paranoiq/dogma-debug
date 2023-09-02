<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint

namespace Dogma\Debug;

use function hexdec;
use function ltrim;
use function preg_replace;
use function str_pad;
use function strlen;
use function strtolower;
use function substr;
use function user_error;
use const E_USER_NOTICE;
use const STR_PAD_RIGHT;

final class Ansi
{

    /** @var bool */
    public static $off = false;

    /** @var string */
    public static $default = self::LGRAY;

    public const WHITE = 'W';
    public const LGRAY = 'w';
    public const DGRAY = 'K';
    public const BLACK = 'k';
    public const LRED = 'R';
    public const DRED = 'r';
    public const LGREEN = 'G';
    public const DGREEN = 'g';
    public const LBLUE = 'B';
    public const DBLUE = 'b';
    public const LCYAN = 'C';
    public const DCYAN = 'c';
    public const LMAGENTA = 'M';
    public const DMAGENTA = 'm';
    public const LYELLOW = 'Y';
    public const DYELLOW = 'y';

    // alias to MAGENTA
    public const LPURPLE = 'M';
    public const DPURPLE = 'm';

    public const RESET_FORMAT = "\x1B[0m";
    public const UP = "\x1B[A";
    public const DELETE_ROW = "\x1B[2K";

    /** @var string[] */
    private static $fg = [
        self::WHITE => '1;37',
        self::LGRAY => '0;37',
        self::DGRAY => '1;30',
        self::BLACK => '0;30',

        self::DRED => '0;31',
        self::LRED => '1;31',
        self::DGREEN => '0;32',
        self::LGREEN => '1;32',
        self::DBLUE => '0;34',
        self::LBLUE => '1;34',

        self::DCYAN => '0;36',
        self::LCYAN => '1;36',
        self::DMAGENTA => '0;35',
        self::LMAGENTA => '1;35',
        self::DYELLOW => '0;33',
        self::LYELLOW => '1;33',
    ];

    /** @var string[] */
    private static $bg = [
        self::LGRAY => '47',
        self::BLACK => '40',

        self::DRED => '41',
        self::DGREEN => '42',
        self::DBLUE => '44',

        self::DYELLOW => '43',
        self::DMAGENTA => '45',
        self::DCYAN => '46',

        // aliases
        self::WHITE => '47',
        self::DGRAY => '40',

        self::LRED => '41',
        self::LGREEN => '42',
        self::LBLUE => '44',

        self::LYELLOW => '43',
        self::LMAGENTA => '45',
        self::LCYAN => '46',
    ];

    public static function color($string, ?string $color = null, ?string $background = null): string
    {
        $string = (string) $string;

        if (self::$off || ($background === null && $color === self::$default)) {
            return $string;
        }

        $out = '';
        if (isset(self::$fg[$color])) {
            $out .= "\x1B[" . self::$fg[$color] . 'm';
        }
        if (isset(self::$bg[$background])) {
            $out .= "\x1B[" . self::$bg[$background] . 'm';
        }

        $end = $background === null
            ? "\x1B[" . self::$fg[self::$default] . "m" // does not reset background
            : "\x1B[0m";

        return $out . $string . $end;
    }

    public static function colorStart(string $color): string
    {
        return "\x1B[" . self::$fg[$color] . "m";
    }

    /**
     * @param int|float|string $string
     */
    public static function rgb($string, ?string $color, ?string $background = null): string
    {
        $string = (string) $string;

        $color = $color ? strtolower(ltrim($color, "#")) : Color::NAMED_COLORS[$background ? 'black' : 'silver'];
        $color = Color::NAMED_COLORS[$color] ?? $color;
        if (strlen($color) === 3) {
            $r = hexdec($color[0] . $color[0]);
            $g = hexdec($color[1] . $color[1]);
            $b = hexdec($color[2] . $color[2]);
        } elseif (strlen($color) === 6) {
            $r = hexdec(substr($color, 0, 2));
            $g = hexdec(substr($color, 2, 2));
            $b = hexdec(substr($color, 4, 2));
        } else {
            user_error("Invalid color: #{$color}", E_USER_NOTICE);

            return $string;
        }
        if ($background === null) {
            return "\x1B[38;2;{$r};{$g};{$b}m{$string}\x1B[0m";
        }

        $background = strtolower(ltrim($background, "#"));
        $background = Color::NAMED_COLORS[$background] ?? $background;
        if (strlen($background) === 3) {
            $rb = hexdec($background[0] . $background[0]);
            $gb = hexdec($background[1] . $background[1]);
            $bb = hexdec($background[2] . $background[2]);
        } elseif (strlen($background) === 6) {
            $rb = hexdec(substr($background, 0, 2));
            $gb = hexdec(substr($background, 2, 2));
            $bb = hexdec(substr($background, 4, 2));
        } else {
            user_error("Invalid background color: #{$background}", E_USER_NOTICE);

            return $string;
        }

        return "\x1B[38;2;{$r};{$g};{$b}m\x1B[48;2;{$rb};{$gb};{$bb}m{$string}\x1B[0m";
    }

    public static function between($string, string $color, string $after, string $background = self::BLACK): string
    {
        if (self::$off || $color === $after) {
            return (string) $string;
        }

        if ($background === self::BLACK) {
            return "\x1B[" . self::$fg[$color] . 'm' . $string . "\x1B[" . self::$fg[$after] . 'm';
        } else {
            return "\x1B[" . self::$fg[$color] . "m\x1B[" . self::$bg[$background] . 'm' . $string . "\x1B[" . self::$fg[$after] . "m\x1B[" . self::$bg[$background] . 'm';
        }
    }

    public static function background(string $string, string $background): string
    {
        return self::color($string, null, $background);
    }

    public static function length(string $string, string $encoding = 'utf-8'): int
    {
        return Str::length(self::removeColors($string), $encoding);
    }

    public static function pad(string $string, int $length, string $with = ' ', int $type = STR_PAD_RIGHT): string
    {
        $original = self::removeColors($string);

        return str_pad($string, $length + strlen($string) - strlen($original) + 1, $with, $type);
    }

    public static function removeColors(string $string): string
    {
        return (string) preg_replace('/\\x1B\\[[^m]+m/U', '', $string);
    }

    // shortcuts -------------------------------------------------------------------------------------------------------

    public static function white($string, ?string $background = null): string
    {
        return self::color($string, self::WHITE, $background);
    }

    public static function lgray($string, ?string $background = null): string
    {
        return self::color($string, self::LGRAY, $background);
    }

    public static function dgray($string, ?string $background = null): string
    {
        return self::color($string, self::DGRAY, $background);
    }

    public static function black($string, ?string $background = null): string
    {
        return self::color($string, self::BLACK, $background);
    }

    public static function lred($string, ?string $background = null): string
    {
        return self::color($string, self::LRED, $background);
    }

    public static function dred($string, ?string $background = null): string
    {
        return self::color($string, self::DRED, $background);
    }

    public static function lgreen($string, ?string $background = null): string
    {
        return self::color($string, self::LGREEN, $background);
    }

    public static function dgreen($string, ?string $background = null): string
    {
        return self::color($string, self::DGREEN, $background);
    }

    public static function lblue($string, ?string $background = null): string
    {
        return self::color($string, self::LBLUE, $background);
    }

    public static function dblue($string, ?string $background = null): string
    {
        return self::color($string, self::DBLUE, $background);
    }

    public static function lcyan($string, ?string $background = null): string
    {
        return self::color($string, self::LCYAN, $background);
    }

    public static function dcyan($string, ?string $background = null): string
    {
        return self::color($string, self::DCYAN, $background);
    }

    public static function lmagenta($string, ?string $background = null): string
    {
        return self::color($string, self::LMAGENTA, $background);
    }

    public static function dmagenta($string, ?string $background = null): string
    {
        return self::color($string, self::DMAGENTA, $background);
    }

    public static function lyellow($string, ?string $background = null): string
    {
        return self::color($string, self::LYELLOW, $background);
    }

    public static function dyellow($string, ?string $background = null): string
    {
        return self::color($string, self::DYELLOW, $background);
    }

    // alias to lmagenta
    public static function lpurple($string, ?string $background = null): string
    {
        return self::color($string, self::LMAGENTA, $background);
    }

    // alias to dmagenta
    public static function purple($string, ?string $background = null): string
    {
        return self::color($string, self::DMAGENTA, $background);
    }

}
