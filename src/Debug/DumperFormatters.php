<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Dogma\Debug\Ansi as A;
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
        'null' => A::LYELLOW, // null
        'bool' => A::LYELLOW, // true, false
        'int' => A::LYELLOW, // 123
        'float' => A::LYELLOW, // 123.4

        'value' => A::LYELLOW, // primary color for formatted internal value of an object
        'value2' => A::DYELLOW, // secondary color for formatted internal value of an object

        'string' => A::LCYAN, // "foo"
        'escape' => A::DCYAN, // "\n"

        'resource' => A::LRED, // stream
        'namespace' => A::LRED, // Foo...
        'backslash' => A::DGRAY, // // ...\...
        'name' => A::LRED, // ...Bar
        'access' => A::DGRAY, // public private protected
        'property' => A::WHITE, // $foo
        'key' => A::WHITE, // array keys. set null to use string/int formats

        'closure' => A::LGRAY, // static function ($a) use ($b)
        'parameter' => A::WHITE, // $a, $b

        'path' => A::DGRAY, // C:/foo/bar/...
        'file' => A::LGRAY, // .../baz.php
        'line' => A::DGRAY, // :42

        'bracket' => A::WHITE, // [ ] { } ( )
        'symbol' => A::LGRAY, // , ; :: => =
        'indent' => A::DGRAY, // |
        'info' => A::DGRAY, // // 5 items

        'exceptions' => A::LMAGENTA, // RECURSION, ... (max depth, not traversed)

        'function' => A::LGREEN, // stream wrapper function call
        'time' => A::LBLUE, // stream wrapper operation time
    ];

    public static function null(string $value): string
    {
        return A::color($value, self::$colors['null']);
    }

    public static function bool(string $value): string
    {
        return A::color($value, self::$colors['bool']);
    }

    public static function int(string $value): string
    {
        return A::color($value, self::$colors['int']);
    }

    public static function float(string $value): string
    {
        return A::color($value, self::$colors['float']);
    }

    public static function value(string $value): string
    {
        return A::color($value, self::$colors['value']);
    }

    public static function value2(string $value): string
    {
        return A::color($value, self::$colors['value2']);
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
            return A::between($table[$m[1]], self::$colors['escape'], self::$colors['string']);
        }, $string);

        return A::color($quote . $escaped . $quote, self::$colors['string']);
    }

    // escape

    public static function symbol(string $symbol): string
    {
        return A::color($symbol, self::$colors['symbol']);
    }

    public static function bracket(string $bracket): string
    {
        return A::color($bracket, self::$colors['bracket']);
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
            return A::color($key, self::$colors['key']);
        } elseif (is_int($key)) {
            return self::int((string) $key);
        } else {
            return self::string($key);
        }
    }

    public static function info(string $info): string
    {
        return A::color($info, self::$colors['info']);
    }

    public static function infoPrefix(): string
    {
        return ' ' . A::colorStart(self::$colors['info']) . '// ';
    }

    public static function exceptions($string): string
    {
        return A::color($string, self::$colors['exceptions']);
    }

    public static function indent(int $depth): string
    {
        return $depth > 1
            ? '   ' . str_repeat(A::color('|', self::$colors['indent']) . '  ', $depth - 1)
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
            return A::color($name, self::$colors['namespace']);
        }, $names);

        $names[] = A::color($class, self::$colors['name']);

        return implode(A::color('\\', self::$colors['backslash']), $names);
    }

    public static function nameDim(string $class): string
    {
        $names = explode('\\', $class);
        $class = array_pop($names);

        $names = array_map(static function ($name): string {
            return A::color($name, self::$colors['info']);
        }, $names);

        $names[] = A::color($class, self::$colors['symbol']);

        return implode(A::color('\\', self::$colors['backslash']), $names);
    }

    public static function access($string): string
    {
        return A::color($string, self::$colors['access']);
    }

    public static function property($string): string
    {
        return A::color($string, self::$colors['property']);
    }

    public static function resource($string): string
    {
        return A::color($string, self::$colors['resource']);
    }

    public static function closure($string): string
    {
        return A::color(preg_replace_callback('/(\\$[A-Za-z0-9_]+)/', static function ($m): string {
            return A::between($m[1], self::$colors['parameter'], self::$colors['closure']);
        }, $string), self::$colors['closure']);
    }

    public static function file(string $file): string
    {
        $dirName = str_replace('\\', '/', dirname($file)) . '/';
        $fileName = basename($file);

        if (self::$trimPathPrefix && substr($dirName, 0, strlen(self::$trimPathPrefix)) === self::$trimPathPrefix) {
            $dirName = substr($dirName, strlen(self::$trimPathPrefix));
        }

        return A::color($dirName, self::$colors['path'])
            . A::color($fileName, self::$colors['file']);
    }

    public static function fileLine(string $file, int $line): string
    {
        $dirName = str_replace('\\', '/', dirname($file)) . '/';
        $fileName = basename($file);

        if (self::$trimPathPrefix && substr($dirName, 0, strlen(self::$trimPathPrefix)) === self::$trimPathPrefix) {
            $dirName = substr($dirName, strlen(self::$trimPathPrefix));
        }

        return A::color($dirName, self::$colors['path'])
            . A::color($fileName, self::$colors['file'])
            . A::color(':', self::$colors['info'])
            . A::color((string) $line, self::$colors['line']);
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
        $params = implode(A::color(', ', self::$colors['function']), $formatted);

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
                    $output[] = A::color($k . ':', self::$colors['function']) . ' ' . self::dumpValue($v);
                }
            }
            $output = ' ' . implode(' ', $output);
            $end = '):';
        }

        self::$showInfo = $info;

        return A::color($name . '(', self::$colors['function']) . $params . A::color($end, self::$colors['function']) . $output;
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
