<?php declare(strict_types = 1);

namespace Dogma\Debug;

use Dogma\Debug\Colors as C;
use function array_keys;
use function array_map;
use function array_pop;
use function array_values;
use function basename;
use function dirname;
use function explode;
use function implode;
use function is_int;
use function preg_replace;
use function preg_replace_callback;
use function str_repeat;
use function str_replace;
use function strlen;
use function substr;

trait DumperFormatters
{

    /** @var array<string, string> */
    public static $colors = [
        'null' => C::LYELLOW, // null
        'bool' => C::LYELLOW, // true, false
        'int' => C::LYELLOW, // 123
        'float' => C::LYELLOW, // 123.4

        'value' => C::LYELLOW, // primary color for formatted internal value of an object
        'value2' => C::DYELLOW, // secondary color for formatted internal value of an object

        'string' => C::LCYAN, // "foo"
        'escape' => C::DCYAN, // "\n"

        'resource' => C::LRED, // stream
        'namespace' => C::LRED, // Foo...
        'backslash' => C::DGRAY, // // ...\...
        'name' => C::LRED, // ...Bar
        'access' => C::DGRAY, // public private protected
        'property' => C::WHITE, // $foo
        'key' => C::WHITE, // array keys. set null to use string/int formats

        'closure' => C::LGRAY, // static function ($a) use ($b)
        'parameter' => C::WHITE, // $a, $b

        'path' => C::DGRAY, // C:/foo/bar/...
        'file' => C::LGRAY, // .../baz.php
        'line' => C::DGRAY, // :42

        'bracket' => C::WHITE, // [ ] { } ( )
        'symbol' => C::LGRAY, // , ; :: => =
        'indent' => C::DGRAY, // |
        'info' => C::DGRAY, // // 5 items

        'exceptions' => C::LMAGENTA, // RECURSION, ... (max depth, not traversed)
    ];

    public static function null(string $value): string
    {
        return C::color($value, self::$colors['null']);
    }

    public static function bool(string $value): string
    {
        return C::color($value, self::$colors['bool']);
    }

    public static function int(string $value): string
    {
        return C::color($value, self::$colors['int']);
    }

    public static function float(string $value): string
    {
        return C::color($value, self::$colors['float']);
    }

    public static function value(string $value): string
    {
        return C::color($value, self::$colors['value']);
    }

    public static function value2(string $value): string
    {
        return C::color($value, self::$colors['value2']);
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
            return C::between($table[$m[1]], self::$colors['escape'], self::$colors['string']);
        }, $string);

        return C::color($quote . $escaped . $quote, self::$colors['string']);
    }

    // escape

    public static function symbol(string $symbol): string
    {
        return C::color($symbol, self::$colors['symbol']);
    }

    public static function bracket(string $bracket): string
    {
        return C::color($bracket, self::$colors['bracket']);
    }

    /**
     * @param int|string $key
     * @return string
     */
    public static function key($key): string
    {
        if (self::$colors['key'] !== null) {
            // todo: string key escaping
            return C::color($key, self::$colors['key']);
        } elseif (is_int($key)) {
            return self::int((string) $key);
        } else {
            return self::string($key);
        }
    }

    public static function info(string $info): string
    {
        return C::color($info, self::$colors['info']);
    }

    public static function infoPrefix(): string
    {
        return " \x1B[" . C::ansiValue(self::$colors['info']) . "m// ";
    }

    public static function exceptions($string): string
    {
        return C::color($string, self::$colors['exceptions']);
    }

    public static function indent(int $depth): string
    {
        return $depth > 1
            ? '   ' . str_repeat(C::color('|', self::$colors['indent']) . '  ', $depth - 1)
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
            return C::color($name, self::$colors['namespace']);
        }, $names);

        $names[] = C::color($class, self::$colors['name']);

        return implode(C::color('\\', self::$colors['backslash']), $names);
    }

    public static function nameDim(string $class): string
    {
        $names = explode('\\', $class);
        $class = array_pop($names);

        $names = array_map(static function ($name): string {
            return C::color($name, self::$colors['info']);
        }, $names);

        $names[] = C::color($class, self::$colors['symbol']);

        return implode(C::color('\\', self::$colors['backslash']), $names);
    }

    public static function access($string): string
    {
        return C::color($string, self::$colors['access']);
    }

    public static function property($string): string
    {
        return C::color($string, self::$colors['property']);
    }

    public static function resource($string): string
    {
        return C::color($string, self::$colors['resource']);
    }

    public static function closure($string): string
    {
        return C::color(preg_replace_callback('/(\\$[A-Za-z0-9_]+)/', static function ($m): string {
            return C::between($m[1], self::$colors['parameter'], self::$colors['closure']);
        }, $string), self::$colors['closure']);
    }

    public static function file(string $file): string
    {
        $dirName = str_replace('\\', '/', dirname($file)) . '/';
        $fileName = basename($file);

        if (self::$trimPathPrefix && substr($dirName, 0, strlen(self::$trimPathPrefix)) === self::$trimPathPrefix) {
            $dirName = substr($dirName, strlen(self::$trimPathPrefix));
        }

        return C::color($dirName, self::$colors['path'])
            . C::color($fileName, self::$colors['file']);
    }

    public static function fileLine(string $file, int $line): string
    {
        $dirName = str_replace('\\', '/', dirname($file)) . '/';
        $fileName = basename($file);

        if (self::$trimPathPrefix && substr($dirName, 0, strlen(self::$trimPathPrefix)) === self::$trimPathPrefix) {
            $dirName = substr($dirName, strlen(self::$trimPathPrefix));
        }

        return C::color($dirName, self::$colors['path'])
            . C::color($fileName, self::$colors['file'])
            . C::color(':', self::$colors['info'])
            . C::color((string) $line, self::$colors['line']);
    }

}
