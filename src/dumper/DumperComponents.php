<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable Squiz.Arrays.ArrayDeclaration.IndexNoNewline
// spell-check-ignore: xaa xab xac xad xae xaf xba xbb xbc xbd xbe xbf xca xcb xcc xcd xce xcf xda xdb xdc xdd xde xdf xea xeb xec xed xee xef xfa xfb xfc xfd xfe xff ª µ º Ä Å Æ Ç É Ñ Ö Ü ß à á â ä å æ ç è é ê ë ì í î ï ñ ò ó ô ö ù ú û ü ÿ ƒ Γ Θ Σ Φ Ω α δ ε π σ τ φ ⁿ

namespace Dogma\Debug;

use DateTime;
use DateTimeZone;
use function abs;
use function array_keys;
use function array_map;
use function array_pop;
use function array_values;
use function basename;
use function bin2hex;
use function chr;
use function count;
use function dechex;
use function dirname;
use function end;
use function explode;
use function function_exists;
use function hexdec;
use function implode;
use function ini_get;
use function is_array;
use function is_infinite;
use function is_int;
use function is_nan;
use function is_object;
use function is_resource;
use function is_scalar;
use function is_string;
use function json_encode;
use function key;
use function md5;
use function number_format;
use function ord;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function rtrim;
use function spl_object_hash;
use function spl_object_id;
use function str_contains;
use function str_pad;
use function str_repeat;
use function str_replace;
use function str_split;
use function strlen;
use function strpos;
use function strrev;
use function strtoupper;
use function substr;
use function trim;
use const PHP_VERSION_ID;
use const STR_PAD_LEFT;

trait DumperComponents
{

    /** @var array<string, string> */
    private static $phpEscapes = [
        "\0" => '\0', // 00
        "\t" => '\t', // 09
        "\n" => '\n', // 0a
        "\v" => '\v', // 0b
        "\f" => '\f', // 0c
        "\r" => '\r', // 0d
        "\e" => '\e', // 1b
        '\\' => '\\\\',
        '$' => '\$',
        '"' => '\"',
    ];

    /** @var array<string, string> */
    private static $jsEscapes = [
        "\x00" => '\0',
        "\x08" => '\b',
        "\f" => '\f', // 0c
        "\n" => '\n', // 0a
        "\r" => '\r', // 0d
        "\t" => '\t', // 09
        "\v" => '\v', // 0b
        '\\' => '\\\\',
        '"' => '\"',
    ];

    /** @var array<string, string> */
    private static $jsonEscapes = [
        "\x08" => '\b',
        "\f" => '\f', // 0c
        "\n" => '\n', // 0a
        "\r" => '\r', // 0d
        "\t" => '\t', // 09
        '\\' => '\\\\',
        '"' => '\"',
    ];

    /** @var array<string, string> */
    private static $mysqlEscapes = [
        "\x00" => '\0',
        "\x08" => '\b',
        "\n" => '\n', // 0a
        "\r" => '\r', // 0d
        "\t" => '\t', // 09
        "\x1a" => '\Z', // 1a (legacy Win EOF)
        '\\' => '\\\\',
        "'" => "''",
        '"' => '""',
    ];

    /** @var array<string, string> */
    private static $pgsqlEscapes = [
        "\x08" => '\b',
        "\f" => '\f', // 0c
        "\n" => '\n', // 0a
        "\r" => '\r', // 0d
        "\t" => '\t', // 09
        '\\' => '\\\\',
        "'" => "''",
        '"' => '""',
    ];

    /** @var array<string, string> */
    private static $nameEscapes = [
        "\x00" => 'NUL', "\x01" => 'SOH', "\x02" => 'STX', "\x03" => 'ETX', "\x04" => 'EOT', "\x05" => 'ENQ', "\x06" => 'ACK', "\x07" => 'BEL',
        "\x08" => 'BS',  "\x09" => 'TAB', "\x0a" => 'LF',  "\x0b" => 'VT',  "\x0c" => 'FF',  "\x0d" => 'CR',  "\x0e" => 'SO',  "\x0f" => 'SI',
        "\x10" => 'DLE', "\x11" => 'DC1', "\x12" => 'DC2', "\x13" => 'DC3', "\x14" => 'DC4', "\x15" => 'NAK', "\x16" => 'SYN', "\x17" => 'ETB',
        "\x18" => 'CAN', "\x19" => 'EM',  "\x1a" => 'SUB', "\x1b" => 'ESC', "\x1c" => 'FS',  "\x1d" => 'GS',  "\x1e" => 'RS',  "\x1f" => 'US',
        "\x7f" => 'DEL',
    ];

    /** @var array<string, string> */
    private static $isoEscapes = [
        "\x00" => '⎕', "\x01" => '⌈', "\x02" => '⊥', "\x03" => '⌋', "\x04" => '⌁', "\x05" => '⊠', "\x06" => '✓', "\x07" => '⍾',
        "\x08" => '⤺', "\x09" => '⪫', "\x0a" => '≡', "\x0b" => '⩛', "\x0c" => '↡', "\x0d" => '⪪', "\x0e" => '⊗', "\x0f" => '⊙',
        "\x10" => '⊟', "\x11" => '◷', "\x12" => '◶', "\x13" => '◵', "\x14" => '◴', "\x15" => '⍻', "\x16" => '⎍', "\x17" => '⊣',
        "\x18" => '⧖', "\x19" => '⍿', "\x1a" => '␦', "\x1b" => '⊖', "\x1c" => '◰', "\x1d" => '◱', "\x1e" => '◲', "\x1f" => '◳',
        "\x20" => '△',
        "\x7f" => '▨',
    ];

    /** @var array<string, string> */
    private static $cp437Escapes = [
        "\x00" => '¤', "\x01" => '☺', "\x02" => '☻', "\x03" => '♥', "\x04" => '♦', "\x05" => '♣', "\x06" => '♠', "\x07" => '•',
        "\x08" => '◘', "\x09" => '○', "\x0a" => '◙', "\x0b" => '♂', "\x0c" => '♀', "\x0d" => '♪', "\x0e" => '♫', "\x0f" => '☼',
        "\x10" => '►', "\x11" => '◄', "\x12" => '↕', "\x13" => '‼', "\x14" => '¶', "\x15" => '§', "\x16" => '▬', "\x17" => '↨',
        "\x18" => '↑', "\x19" => '↓', "\x1a" => '→', "\x1b" => '←', "\x1c" => '∟', "\x1d" => '↔', "\x1e" => '▲', "\x1f" => '▼',
        "\x7f" => '⌂',
        "\x80" => 'Ç', "\x81" => 'ü', "\x82" => 'é', "\x83" => 'â', "\x84" => 'ä', "\x85" => 'à', "\x86" => 'å', "\x87" => 'ç',
        "\x88" => 'ê', "\x89" => 'ë', "\x8a" => 'è', "\x8b" => 'ï', "\x8c" => 'î', "\x8d" => 'ì', "\x8e" => 'Ä', "\x8f" => 'Å',
        "\x90" => 'É', "\x91" => 'æ', "\x92" => 'Æ', "\x93" => 'ô', "\x94" => 'ö', "\x95" => 'ò', "\x96" => 'û', "\x97" => 'ù',
        "\x98" => 'ÿ', "\x99" => 'Ö', "\x9a" => 'Ü', "\x9b" => '¢', "\x9c" => '£', "\x9d" => '¥', "\x9e" => '₧', "\x9f" => 'ƒ',
        "\xa0" => 'á', "\xa1" => 'í', "\xa2" => 'ó', "\xa3" => 'ú', "\xa4" => 'ñ', "\xa5" => 'Ñ', "\xa6" => 'ª', "\xa7" => 'º',
        "\xa8" => '¿', "\xa9" => '⌐', "\xaa" => '¬', "\xab" => '½', "\xac" => '¼', "\xad" => '¡', "\xae" => '«', "\xaf" => '»',
        "\xb0" => '░', "\xb1" => '▒', "\xb2" => '▓', "\xb3" => '│', "\xb4" => '┤', "\xb5" => '╡', "\xb6" => '╢', "\xb7" => '╖',
        "\xb8" => '╕', "\xb9" => '╣', "\xba" => '║', "\xbb" => '╗', "\xbc" => '╝', "\xbd" => '╜', "\xbe" => '╛', "\xbf" => '┐',
        "\xc0" => '└', "\xc1" => '┴', "\xc2" => '┬', "\xc3" => '├', "\xc4" => '─', "\xc5" => '┼', "\xc6" => '╞', "\xc7" => '╟',
        "\xc8" => '╚', "\xc9" => '╔', "\xca" => '╩', "\xcb" => '╦', "\xcc" => '╠', "\xcd" => '═', "\xce" => '╬', "\xcf" => '╧',
        "\xd0" => '╨', "\xd1" => '╤', "\xd2" => '╥', "\xd3" => '╙', "\xd4" => '╘', "\xd5" => '╒', "\xd6" => '╓', "\xd7" => '╫',
        "\xd8" => '╪', "\xd9" => '┘', "\xda" => '┌', "\xdb" => '█', "\xdc" => '▄', "\xdd" => '▌', "\xde" => '▐', "\xdf" => '▀',
        "\xe0" => 'α', "\xe1" => 'ß', "\xe2" => 'Γ', "\xe3" => 'π', "\xe4" => 'Σ', "\xe5" => 'σ', "\xe6" => 'µ', "\xe7" => 'τ',
        "\xe8" => 'Φ', "\xe9" => 'Θ', "\xea" => 'Ω', "\xeb" => 'δ', "\xec" => '∞', "\xed" => 'φ', "\xee" => 'ε', "\xef" => '∩',
        "\xf0" => '≡', "\xf1" => '±', "\xf2" => '≥', "\xf3" => '≤', "\xf4" => '⌠', "\xf5" => '⌡', "\xf6" => '÷', "\xf7" => '≈',
        "\xf8" => '°', "\xf9" => '∙', "\xfa" => '·', "\xfb" => '√', "\xfc" => 'ⁿ', "\xfd" => '²', "\xfe" => '■', "\xff" => ' ',
    ];

    public static function null(string $value): string
    {
        return Ansi::color($value, self::$colors['null']);
    }

    public static function bool(string $value): string
    {
        return Ansi::color($value, self::$colors['bool']);
    }

    public static function int(string $value): string
    {
        $under = self::$numbersWithUnderscore ?? (PHP_VERSION_ID >= 70400);
        if ($under) {
            $value = strrev(implode('_', str_split(strrev($value), 3)));
        }

        return Ansi::color($value, self::$colors['int']);
    }

    public static function float(float $value): string
    {
        if (!is_nan($value) && !is_infinite($value) && self::$floatFormatting !== self::FLOATS_DEFAULT) {
            if (self::$floatFormatting === self::FLOATS_DECIMALS) {
                $value = number_format($value, 16, '.', '');
                $value = rtrim($value, '0');
                if (substr($value, -1) === '.') {
                    $value .= '0';
                }
            } else {
                $value = self::floatScientific3($value);
            }
        } else {
            $value = (string) $value;
        }

        $value = strtoupper($value);
        if ($value !== 'INF' && $value !== '-INF' && $value !== 'NAN' && !str_contains($value, '.')) {
            $value .= '.0';
        }
        $under = self::$numbersWithUnderscore ?? (PHP_VERSION_ID >= 70400);
        if ($under) {
            [$int, $decimal] = explode('.', $value);
            $value = strrev(implode('_', str_split(strrev($int), 3))) . '.' . implode('_', str_split($decimal, 3));
        }

        return Ansi::color($value, self::$colors['float']);
    }

    private static function floatScientific3(float $value): string {
        $exponent = 0;
        while (abs($value) >= 1000.0 || abs($value) < 1.0) {
            if ($value === 0.0 || $value === -0.0) {
                break;
            }

            if ($value >= 1000.0) {
                $value /= 1000.0;
                $exponent += 3;
            } elseif ($value < 1) {
                $value *= 1000.0;
                $exponent -= 3;
            }
        }
        if ($exponent === 3) {
            $value *= 1000.0;
            $exponent = 0;
        } elseif ($exponent === -3) {
            $value /= 1000.0;
            $exponent = 0;
        }

        $value = rtrim((string) $value, '0');
        if ($value === '') {
            return '0.0';
        } elseif (substr($value, -1) === '.') {
            $value .= '0';
        } elseif (!str_contains($value, '.')) {
            $value .= '.0';
        }

        return $value . ($exponent ? ('E' . $exponent) : '');
    }

    public static function value(string $value): string
    {
        return Ansi::color($value, self::$colors['value']);
    }

    public static function value2(string $value): string
    {
        return Ansi::color($value, self::$colors['value2']);
    }

    public static function symbol(string $symbol): string
    {
        return Ansi::color($symbol, self::$colors['symbol']);
    }

    public static function parameter(string $parameter): string
    {
        return Ansi::color($parameter, self::$colors['parameter']);
    }

    public static function type(string $type): string
    {
        return Ansi::color($type, self::$colors['type']);
    }

    public static function operator(string $operator): string
    {
        return Ansi::color($operator, self::$colors['operator']);
    }

    public static function reference(string $reference): string
    {
        return Ansi::color($reference, self::$colors['reference']);
    }

    public static function bracket(string $bracket): string
    {
        return Ansi::color($bracket, self::$colors['bracket']);
    }

    /**
     * @param int|string $key
     * @return string
     */
    public static function key($key, bool $noQuote = false): string
    {
        if ($key === '' || (is_string($key) && (
                   (self::$alwaysQuoteStringKeys && !$noQuote)
                || Str::isBinary($key)
                || (!$noQuote && (preg_match('~\\s~', $key) !== 0)))
            )
        ) {
            return self::string($key);
        } elseif (self::$colors['key'] !== null) {
            // todo: string key escaping
            return Ansi::color($key, self::$colors['key']);
        } elseif (is_int($key)) {
            return self::int((string) $key);
        } else {
            return self::string($key);
        }
    }

    public static function info(string $info): string
    {
        return Ansi::color($info, self::$colors['info']);
    }

    /**
     * @return array{string, string}
     */
    public static function splitInfo(string $string): array
    {
        $result = explode(self::infoPrefix(), $string);
        if (count($result) === 1) {
            $result[] = '';
        } else {
            $result[1] = (string) preg_replace("~\x1B\\[[0-9;]+m~", '', $result[1]);
        }

        return [$result[0], $result[1]];
    }

    /**
     * @return non-empty-string
     */
    public static function infoPrefix(): string
    {
        return ' ' . Ansi::colorStart(self::$colors['info']) . '// ';
    }

    public static function exceptions(string $string): string
    {
        return Ansi::color($string, self::$colors['exceptions']);
    }

    public static function indent(int $depth): string
    {
        $baseIndent = str_repeat(' ', self::$indentSpaces - 1);

        return $depth > 1
            ? ' ' . $baseIndent . str_repeat(Ansi::color(self::$indentLines ? '|' : ' ', self::$colors['indent']) . $baseIndent, $depth - 1)
            : ($depth === 1 ? $baseIndent . ' ' : '');
    }

    public static function class(string $class): string
    {
        $short = $class;
        if (self::$namespaceReplacements) {
            $short = preg_replace(array_keys(self::$namespaceReplacements), array_values(self::$namespaceReplacements), $class);
        }

        $names = explode('\\', $short);
        $name = array_pop($names);

        $names = array_map(static function ($name): string {
            return Ansi::color($name, self::$colors['namespace']);
        }, $names);
        $name = Ansi::color($name, self::$colors['class']);
        //$name = Links::class($name, $class);

        $names[] = $name;

        return implode(Ansi::color('\\', self::$colors['backslash']), $names);
    }

    public static function nameDim(string $class): string
    {
        $names = explode('\\', $class);
        $class = array_pop($names);

        $names = array_map(static function ($name): string {
            return Ansi::color($name, self::$colors['info']);
        }, $names);

        $class = preg_replace_callback("~[\\x00-\\x08\\x0B-\\x1A\\x1C-\\x1F]~", static function (array $m): string {
            return Ansi::colorStart(Ansi::WHITE) . '\x' . Str::charToHex($m[0]) . Ansi::colorStart(self::$colors['symbol']);
        }, $class);
        $names[] = Ansi::color($class, self::$colors['symbol']);

        return implode(Ansi::color('\\', self::$colors['backslash']), $names);
    }

    public static function access(string $string): string
    {
        return Ansi::color($string, self::$colors['access']);
    }

    public static function constant(string $string): string
    {
        return Ansi::color($string, self::$colors['constant']);
    }

    public static function property(string $string): string
    {
        return Ansi::color($string, self::$colors['property']);
    }

    public static function function(string $string): string
    {
        return Ansi::color($string, self::$colors['function']);
    }

    public static function case(string $string): string
    {
        return Ansi::color($string, self::$colors['case']);
    }

    public static function resource(string $string): string
    {
        return Ansi::color($string, self::$colors['resource']);
    }

    public static function closure(string $string): string
    {
        return Ansi::color(preg_replace_callback('/(\\$[A-Za-z0-9_]+)/', static function ($m): string {
            return Ansi::between($m[1], self::$colors['parameter'], self::$colors['closure']);
        }, $string), self::$colors['closure']);
    }

    public static function file(string $file): string
    {
        $dirName = self::trimPath(self::normalizePath(dirname($file)));
        $fileName = basename($file);
        $separator = $dirName ? (str_contains($file, '://') ? '//' : '/') : '';

        return Ansi::color($dirName . $separator, self::$colors['path'])
            . Ansi::color($fileName, self::$colors['file']);
    }

    public static function fileLine(string $file, int $line): string
    {
        $dirName = self::trimPath(self::normalizePath(dirname($file))) . '/';
        $fileName = basename($file);

        return Ansi::color($dirName, self::$colors['path'])
            . Ansi::color($fileName, self::$colors['file'])
            . Ansi::color(':', self::$colors['info'])
            . Ansi::color((string) $line, self::$colors['line']);
    }

    public static function url(string $url): string
    {
        $url = (string) preg_replace('/([a-zA-Z0-9_-]+)=/', Ansi::dyellow('$1') . '=', $url);
        $url = (string) preg_replace('/=([a-zA-Z0-9_.-]+)/', '=' . Ansi::lcyan('$1'), $url);
        $url = (string) preg_replace('/[\\/?&=]/', Ansi::dgray('$0'), $url);

        return $url;
    }

    /**
     * @param array<int|string|null> $params
     * @param int|string|mixed[]|bool|null $return
     */
    public static function call(string $function, array $params = [], $return = null): string
    {
        $info = Dumper::$showInfo;
        Dumper::$showInfo = null;

        $formatted = [];
        foreach ($params as $key => $value) {
            $formatted[] = Dumper::dumpValue($value, 0, "{$function}.{$key}");
        }
        $params = implode(Ansi::color(', ', Dumper::$colors['call']), $formatted);

        if ($return === null) {
            $output = '';
            $end = ')';
        } else {
            $output = ' ' . Dumper::dumpValue($return, 0);
            $end = '):';
        }

        Dumper::$showInfo = $info;

        return self::func($function . '(', $params, $end, $output);
    }

    public static function func(string $name, string $params = '', string $end = '', string $return = ''): string
    {
        if ($params || $end || $return) {
            return Ansi::color($name, Dumper::$colors['call']) . $params . Ansi::color($end, Dumper::$colors['call']) . $return;
        } else {
            return Ansi::color($name, Dumper::$colors['call']);
        }
    }

    public static function time(string $time): string
    {
        return Ansi::color($time, self::$colors['time']);
    }

    public static function memory(string $memory): string
    {
        return Ansi::color($memory, self::$colors['memory']);
    }

    /**
     * @param object $object
     */
    public static function objectInfo($object): string
    {
        $info = '';
        if (self::$showInfo) {
            $info = ' ' . self::info('// #' . self::objectHash($object));
        }

        return $info;
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    /**
     * @param object $object
     */
    public static function objectHash($object): string
    {
        return substr(md5(spl_object_hash($object)), 0, 4);
    }

    /**
     * @param object|resource $object
     * @return int
     */
    public static function objectId($object): int
    {
        if (is_object($object)) {
            // PHP >= 7.2
            if (function_exists('spl_object_id')) {
                return spl_object_id($object);
            } else {
                $hash = spl_object_hash($object);
                $hash = substr($hash, 8, 8) . substr($hash, 24, 8);

                return (int) hexdec($hash);
            }
        } else {
            return (int) $object;
        }
    }

    public static function binaryUuidInfo(string $uuid): ?string
    {
        $uuid = bin2hex($uuid);
        $formatted = substr($uuid, 0, 8) . '-'
            . substr($uuid, 8, 4) . '-'
            . substr($uuid, 12, 4) . '-'
            . substr($uuid, 16, 4) . '-'
            . substr($uuid, 20, 12);

        $info = self::uuidInfo(explode('-', $formatted));

        return $info ? $info . ', ' . $formatted : null;
    }

    /**
     * @param string[] $parts
     * @return string|null
     */
    public static function uuidInfo(array $parts): ?string
    {
        [$timeLow, $timeMid, $timeHigh, $sequence] = $parts;
        $version = hexdec($timeHigh[0]) + (hexdec($sequence[0]) & 0b1110) * 16;

        if ($version === 1) {
            /** @var positive-int $time */
            $time = hexdec(substr($timeHigh, 1, 3) . $timeMid . $timeLow);

            return 'UUID v' . $version . ', ' . self::intToFormattedDate((int) $time);
        } elseif ($version < 6) {
            return 'UUID v' . $version;
        } else {
            return null;
        }
    }

    /**
     * @param positive-int $int
     * @return string
     */
    public static function intToFormattedDate(int $int): string
    {
        /** @var DateTime $time */
        $time = DateTime::createFromFormat('UP', $int . 'Z');

        return $time->setTimezone(self::getTimeZone())->format('Y-m-d H:i:sP');
    }

    public static function getTimeZone(): DateTimeZone
    {
        if (self::$infoTimeZone instanceof DateTimeZone) {
            return self::$infoTimeZone;
        } elseif (is_string(self::$infoTimeZone)) {
            return new DateTimeZone(self::$infoTimeZone);
        } else {
            return self::$infoTimeZone = new DateTimeZone(ini_get('date.timezone') ?: 'Z');
        }
    }

    /**
     * @return int[]
     */
    public static function binaryComponents(int $number): array
    {
        $components = [];
        $e = 0;
        do {
            $c = 1 << $e;
            if (($number & $c) !== 0) {
                $components[] = $c;
            }
        } while ($e++ < 64);

        return $components;
    }

    // string formatting -----------------------------------------------------------------------------------------------

    /**
     * Escaping and formatting unicode strings and binary strings
     * (null depth means "avoid chunking of binary data" or "keep it on single line")
     *
     * @param non-empty-string|null $splitBy
     */
    public static function string(string $string, ?int $depth = null, ?string $splitBy = null): string
    {
        $length = strlen($string);
        $ellipsis = '';
        if ($length > self::$maxLength) {
            $string = Str::trim($string, self::$maxLength, self::$inputEncoding);
            $ellipsis = Ansi::between('...', self::$colors['exceptions'], self::$colors['string']);
        }

        $binary = Str::isBinary($string, array_keys(self::getTranslations(self::$stringsEscaping))) !== null;
        $split = false;
        if ($splitBy !== null) {
            $pos = strpos($string, $splitBy);
            if ($pos !== false && $pos !== strlen($string)) {
                $split = true;
            }
        }

        $escaping = $binary && (self::$stringsEscaping !== self::ESCAPING_MYSQL && self::$stringsEscaping !== self::ESCAPING_PGSQL)
            ? self::$binaryEscaping
            : self::$stringsEscaping;
        $translations = self::getTranslations($escaping);
        if (!self::$escapeWhiteSpace) {
            unset($translations["\n"], $translations["\r"], $translations["\t"]);
        }
        $translationsWithoutQuote = $translations;
        unset($translationsWithoutQuote['"'], $translationsWithoutQuote['$']);
        $apos = false;
        if ($escaping === self::ESCAPING_PHP || $escaping === self::ESCAPING_JS) {
            if (str_replace(array_keys($translationsWithoutQuote), '', $string) === $string
                && str_replace('"', '', $string) !== $string
                && str_replace("'", '', $string) === $string
            ) {
                $apos = true;
                $translations = $translationsWithoutQuote;
            }
        } elseif ($escaping === self::ESCAPING_MYSQL || $escaping === self::ESCAPING_PGSQL) {
            $apos = true;
            $translations = $translationsWithoutQuote;
        }
        $pattern = Str::createCharPattern(array_keys($translations));

        if (self::$binaryChunkLength < 1) {
            self::$binaryChunkLength = null;
        }
        if ((!$binary && !$split) || $depth === null || self::$binaryChunkLength === null || $length <= self::$binaryChunkLength) {
            // not chunked (one chunk)
            return self::stringChunk(-1, $string, $escaping, $pattern, $translations, $binary, $ellipsis, $apos ? "'" : '"');
        }

        // chunked
        if ($binary) {
            $chunks = Str::chunksBin($string, self::$binaryChunkLength);
        } else {
            // split
            $chunks = explode($splitBy, $string);
            foreach ($chunks as $i => $chunk) {
                $chunks[$i] = $chunk . $splitBy;
            }
            if (end($chunks) === $splitBy) {
                unset($chunks[key($chunks)]);
            }
        }

        // format & deduplicate
        $last = null;
        $lastCount = 0;
        $formatted = [];
        $offsetChars = strlen((string) strlen($string));
        foreach ($chunks as $i => $chunk) {
            $e = $i === count($chunks) - 1 ? $ellipsis : '';
            $chunk = self::stringChunk($i * self::$binaryChunkLength, $chunk, $escaping, $pattern, $translations, $binary, $e, '"', $offsetChars);
            if ($last === $chunk) {
                $lastCount++;
            } elseif ($lastCount > 1) {
                $formatted[] = $last . ' ' . self::exceptions("... {$lastCount}×");
                $lastCount = 1;
            } elseif ($last !== null) {
                $formatted[] = $last;
                $lastCount = 1;
            } else {
                $lastCount = 1;
            }
            $last = $chunk;
        }
        if ($lastCount > 1) {
            $formatted[] = $last . ' ' . self::exceptions("... {$lastCount}×");
        } else {
            $formatted[] = $last;
        }

        $sep = "\n" . self::indent($depth) . ' ' . self::symbol('.') . ' ';
        $prefix = $binary ? self::exceptions('binary:') . "\n" . self::indent($depth) . '   ' : '';

        return $prefix . implode($sep, $formatted);
    }

    /**
     * @param string[] $translations
     */
    private static function stringChunk(
        int $offset,
        string $string,
        string $escaping,
        string $pattern,
        array $translations,
        bool $binary = false,
        string $ellipsis = '',
        string $quote = '"',
        int $offsetChars = 1
    ): string
    {
        if ($escaping === self::ESCAPING_NONE) {
            $formatted = Ansi::color($quote . $string . $ellipsis . $quote, self::$colors['string']);
        } elseif ($binary && ($escaping === self::ESCAPING_MYSQL || $escaping === self::ESCAPING_PGSQL)) {
            // todo: still may be chunked?
            return Ansi::color('x' . $quote . bin2hex($string) . $ellipsis . $quote, self::$colors['string']);
        } else {
            $formatted = self::escapeStringChunk($string, $escaping, $pattern, $translations, self::$colors['string']);

            $formatted = Ansi::color($quote . $formatted . $ellipsis . $quote, self::$colors['string']);
        }

        // hexadecimal
        if ($binary && self::$binaryWithHexadecimal) {
            if ($offset >= 0) {
                $offsetString = str_pad((string) $offset, $offsetChars, ' ', STR_PAD_LEFT);
                $formatted .= ' ' . self::info('// ' . $offsetString . ': ' . Str::strToHex($string));
            } else {
                $formatted .= ' ' . self::info('// ' . Str::strToHex($string));
            }
        }

        return $formatted;
    }

    public static function escapeRawString(
        string $string,
        string $escaping,
        string $normalColor = Ansi::LGRAY,
        string $background = Ansi::BLACK
    ): string
    {
        $translations = self::getTranslations($escaping);
        unset($translations["\n"], $translations["\r"], $translations["\t"], $translations["\e"]);
        // only escape control characters
        // todo: invalid unicode sequences?
        foreach ($translations as $ord => $char) {
            if ($ord >= 32 && $ord !== 127) {
                unset($translations[$ord]);
            }
        }
        $pattern = Str::createCharPattern(array_keys($translations));

        return self::escapeStringChunk($string, $escaping, $pattern, $translations, $normalColor, $background);
    }

    /**
     * @param array<string, string> $translations
     */
    private static function escapeStringChunk(
        string $string,
        string $escaping,
        string $pattern,
        array $translations,
        string $normalColor,
        string $background = Ansi::BLACK
    ): string
    {
        $formatted = $string;
        $formatted = preg_replace_callback($pattern, static function (array $m) use ($escaping, $translations, $normalColor, $background): string {
            $ch = $m[0];

            $escapeColor = $ch > chr(127)
                ? self::$colors['escape_non_ascii']
                : ($ch === chr(127) || ($ch < chr(32) && $ch !== "\n" && $ch !== "\r" & $ch !== "\t")
                    ? self::$colors['escape_special']
                    : self::$colors['escape_basic']);

            if (isset($translations[$ch])) {
                $ch = $translations[$ch];
            } else {
                $ch = $escaping === self::ESCAPING_JSON
                    ? '\u00' . Str::charToHex($ch)
                    : '\x' . Str::charToHex($ch);
            }

            return Ansi::between($ch, $escapeColor, $normalColor, $background);
        }, $formatted);

        if (self::$escapeAllNonAscii
            && $escaping !== self::ESCAPING_MYSQL // does not support unicode escape codes
            && $escaping !== self::ESCAPING_CP437 // already escaped everything as unicode chars
            && $escaping !== self::ESCAPING_ISO2047_SYMBOLS // control chars already escaped as unicode chars
        ) {
            $unicode = preg_replace_callback('~[\x80-\x{10FFFF}]~u', static function (array $m) use ($escaping, $normalColor, $background): string {
                $ch = $m[0];
                if ($escaping === self::ESCAPING_JS || $escaping === self::ESCAPING_JSON) {
                    /** @var string $code */
                    $code = json_encode($ch);
                    $ch = trim($code, '"');
                } else {
                    $ch = '\u{' . dechex(Str::ord($ch)) . '}';
                }

                return Ansi::between($ch, self::$colors['escape_non_ascii'], $normalColor, $background);
            }, $formatted);

            if ($unicode !== null) {
                $formatted = $unicode;
            } else {
                // unicode escaping failed due to invalid encoding (or other than UTF-8 encoding)
                $formatted = preg_replace_callback('~[\x80-\xFF]~', static function (array $m) use ($escaping, $normalColor, $background): string {
                    $ch = $m[0];
                    if ($escaping === self::ESCAPING_JS || $escaping === self::ESCAPING_JSON) {
                        /** @var string $code */
                        $code = json_encode($ch);
                        $ch = trim($code, '"');
                    } else {
                        $ch = '\x' . dechex(ord($ch));
                    }

                    return Ansi::between($ch, self::$colors['escape_non_ascii'], $normalColor, $background);
                }, $formatted);
            }
        }

        return $formatted;
    }

    /**
     * @return string[]
     */
    private static function getTranslations(string $escaping): array
    {
        $translations = [];
        switch ($escaping) {
            case self::ESCAPING_PHP:
                $translations = self::$phpEscapes;
                break;
            case self::ESCAPING_JS:
                $translations = self::$jsEscapes;
                break;
            case self::ESCAPING_JSON:
                $translations = self::$jsonEscapes;
                break;
            case self::ESCAPING_MYSQL:
                $translations = self::$mysqlEscapes;
                break;
            case self::ESCAPING_PGSQL:
                $translations = self::$pgsqlEscapes;
                break;
            case self::ESCAPING_CHAR_NAMES:
                $translations = self::$nameEscapes;
                break;
            case self::ESCAPING_ISO2047_SYMBOLS:
                $translations = self::$isoEscapes;
                break;
            case self::ESCAPING_CP437:
                $translations = self::$cp437Escapes;
                break;
        }

        return $translations;
    }

}
