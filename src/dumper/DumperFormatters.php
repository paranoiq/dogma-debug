<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function array_keys;
use function array_map;
use function array_pop;
use function array_values;
use function basename;
use function dirname;
use function explode;
use function implode;
use function is_int;
use function is_scalar;
use function preg_replace;
use function preg_replace_callback;
use function round;
use function str_repeat;
use function str_replace;
use function strlen;
use function substr;

trait DumperFormatters
{

    /** @var array<string, string> */
    public static $colors = [
        'null' => Ansi::LYELLOW, // null
        'bool' => Ansi::LYELLOW, // true, false
        'int' => Ansi::LYELLOW, // 123
        'float' => Ansi::LYELLOW, // 123.4

        'value' => Ansi::LYELLOW, // primary color for formatted internal value of an object
        'value2' => Ansi::DYELLOW, // secondary color for formatted internal value of an object

        'string' => Ansi::LCYAN, // "foo"
        'escape' => Ansi::DCYAN, // "\n"

        'resource' => Ansi::LRED, // stream
        'namespace' => Ansi::LRED, // Foo...
        'backslash' => Ansi::DGRAY, // // ...\...
        'name' => Ansi::LRED, // ...Bar
        'access' => Ansi::DGRAY, // public private protected
        'property' => Ansi::WHITE, // $foo
        'key' => Ansi::WHITE, // array keys. set null to use string/int formats

        'closure' => Ansi::LGRAY, // static function ($a) use ($b)
        'parameter' => Ansi::WHITE, // $a, $b

        'path' => Ansi::DGRAY, // C:/foo/bar/...
        'file' => Ansi::LGRAY, // .../baz.php
        'line' => Ansi::DGRAY, // :42

        'bracket' => Ansi::WHITE, // [ ] { } ( )
        'symbol' => Ansi::LGRAY, // , ; :: => =
        'indent' => Ansi::DGRAY, // |
        'info' => Ansi::DGRAY, // // 5 items

        'exceptions' => Ansi::LMAGENTA, // RECURSION, ... (max depth, not traversed)

        'function' => Ansi::LGREEN, // stream wrapper function call
        'time' => Ansi::LBLUE, // stream wrapper operation time
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
        return Ansi::color($value, self::$colors['int']);
    }

    public static function float(string $value): string
    {
        return Ansi::color($value, self::$colors['float']);
    }

    public static function value(string $value): string
    {
        return Ansi::color($value, self::$colors['value']);
    }

    public static function value2(string $value): string
    {
        return Ansi::color($value, self::$colors['value2']);
    }

    public static function string(string $string, string $quote = '"'): string
    {
        $table = [
            "\0" => '\0',
            '\\' => '\\\\',
            '"' => '\"',
            "\r" => '\r',
            "\n" => '\n',
            "\t" => '\t',
            "\e" => '\e',
        ];

        $escaped = preg_replace_callback('/([\0\\\\\\r\\n\\e"])/', static function (array $m) use ($table): string {
            return Ansi::between($table[$m[1]], self::$colors['escape'], self::$colors['string']);
        }, $string);

        return Ansi::color($quote . $escaped . $quote, self::$colors['string']);
    }

    // escape

    public static function symbol(string $symbol): string
    {
        return Ansi::color($symbol, self::$colors['symbol']);
    }

    public static function bracket(string $bracket): string
    {
        return Ansi::color($bracket, self::$colors['bracket']);
    }

    /**
     * @param int|string $key
     * @return string
     */
    public static function key($key): string
    {
        if ($key === '') {
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

    public static function infoPrefix(): string
    {
        return ' ' . Ansi::colorStart(self::$colors['info']) . '// ';
    }

    public static function exceptions($string): string
    {
        return Ansi::color($string, self::$colors['exceptions']);
    }

    public static function indent(int $depth): string
    {
        return $depth > 1
            ? '   ' . str_repeat(Ansi::color('|', self::$colors['indent']) . '  ', $depth - 1)
            : ($depth === 1 ? '   ' : '');
    }

    public static function name(string $class): string
    {
        if (self::$namespaceReplacements) {
            $class = preg_replace(array_keys(self::$namespaceReplacements), array_values(self::$namespaceReplacements), $class);
        }

        $names = explode('\\', $class);
        $class = array_pop($names);

        $names = array_map(static function ($name): string {
            return Ansi::color($name, self::$colors['namespace']);
        }, $names);

        $names[] = Ansi::color($class, self::$colors['name']);

        return implode(Ansi::color('\\', self::$colors['backslash']), $names);
    }

    public static function nameDim(string $class): string
    {
        $names = explode('\\', $class);
        $class = array_pop($names);

        $names = array_map(static function ($name): string {
            return Ansi::color($name, self::$colors['info']);
        }, $names);

        $names[] = Ansi::color($class, self::$colors['symbol']);

        return implode(Ansi::color('\\', self::$colors['backslash']), $names);
    }

    public static function access($string): string
    {
        return Ansi::color($string, self::$colors['access']);
    }

    public static function property($string): string
    {
        return Ansi::color($string, self::$colors['property']);
    }

    public static function resource($string): string
    {
        return Ansi::color($string, self::$colors['resource']);
    }

    public static function closure($string): string
    {
        return Ansi::color(preg_replace_callback('/(\\$[A-Za-z0-9_]+)/', static function ($m): string {
            return Ansi::between($m[1], self::$colors['parameter'], self::$colors['closure']);
        }, $string), self::$colors['closure']);
    }

    public static function file(string $file): string
    {
        $dirName = str_replace('\\', '/', dirname($file)) . '/';
        $fileName = basename($file);

        if (self::$trimPathPrefix && substr($dirName, 0, strlen(self::$trimPathPrefix)) === self::$trimPathPrefix) {
            $dirName = substr($dirName, strlen(self::$trimPathPrefix));
        }

        return Ansi::color($dirName, self::$colors['path'])
            . Ansi::color($fileName, self::$colors['file']);
    }

    public static function fileLine(string $file, int $line): string
    {
        $dirName = str_replace('\\', '/', dirname($file)) . '/';
        $fileName = basename($file);

        if (self::$trimPathPrefix && substr($dirName, 0, strlen(self::$trimPathPrefix)) === self::$trimPathPrefix) {
            $dirName = substr($dirName, strlen(self::$trimPathPrefix));
        }

        return Ansi::color($dirName, self::$colors['path'])
            . Ansi::color($fileName, self::$colors['file'])
            . Ansi::color(':', self::$colors['info'])
            . Ansi::color((string) $line, self::$colors['line']);
    }

    /**
     * @param string $name
     * @param string[] $params
     * @param string|string[]|null $return
     * @param mixed[] $hints
     * @return string
     */
    public static function wrapperCall(string $name, array $params = [], $return = null, array $hints = []): string
    {
        $info = self::$showInfo;
        self::$showInfo = null;

        $formatted = [];
        foreach ($params as $key => $value) {
            $key = is_int($key) ? null : $key;
            $formatted[] = self::dumpValue($value, 0, $key);
        }
        $params = implode(Ansi::color(', ', self::$colors['function']), $formatted);

        if ($return === null) {
            $output = '';
            $end = ')';
        } elseif (is_scalar($return)) {
            $output = ' ' . self::dumpValue($return);
            $end = '):';
        } else {
            $output = [];
            foreach ($return as $k => $v) {
                if (is_int($k)) {
                    $output[] = self::dumpValue($v);
                } else {
                    $output[] = Ansi::color($k . ':', self::$colors['function']) . ' ' . self::dumpValue($v);
                }
            }
            $output = ' ' . implode(' ', $output);
            $end = '):';
        }

        self::$showInfo = $info;

        return Ansi::color($name . '(', self::$colors['function']) . $params . Ansi::color($end, self::$colors['function']) . $output;
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    public static function size(int $size): string
    {
        if ($size >= 2**60) {
            return round($size / 2**60,1) . ' ZB';
        } elseif ($size >= 2**50) {
            return round($size / 2**50,1) . ' EB';
        } elseif ($size >= 2**40) {
            return round($size / 2**40,1) . ' TB';
        } elseif ($size >= 2**30) {
            return round($size / 2**30,1) . ' GB';
        } elseif ($size >= 2**20) {
            return round($size / 2**20,1) . ' MB';
        } elseif($size >= 2**10) {
            return round($size / 2**10,1) . ' KB';
        } else {
            return $size . ' B';
        }
    }

}
