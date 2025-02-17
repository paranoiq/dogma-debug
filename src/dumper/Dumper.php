<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// spell-check-ignore: xff

namespace Dogma\Debug;

use BackedEnum;
use Closure;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use IteratorIterator;
use LogicException;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use OuterIterator;
use ReflectionClass;
use ReflectionFunction;
use ReflectionObject;
use ReflectionProperty;
use Throwable;
use UnitEnum;
use WeakReference;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_shift;
use function array_slice;
use function array_unshift;
use function count;
use function explode;
use function file;
use function get_class;
use function get_resource_type;
use function gettype;
use function implode;
use function in_array;
use function is_a;
use function is_array;
use function is_callable;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function is_string;
use function ksort;
use function ob_get_clean;
use function ob_start;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function range;
use function sort;
use function spl_object_hash;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;
use function uniqid;
use function var_dump;
use const PHP_INT_MAX;
use const PHP_INT_MIN;
use const PHP_VERSION_ID;

class Dumper
{
    use DumperTraces;
    use DumperComponents;

    public const FLOATS_DEFAULT = 0;
    public const FLOATS_DECIMALS = 1; // show decimals (do not allow scientific notation)
    public const FLOATS_SCIENTIFIC_3 = 2; // only allow scientific notation with exponent in steps of 3
    //public const FLOATS_LOWERCASE_E = 4;

    public const ESCAPING_NONE = 'none';
    public const ESCAPING_PHP = 'php';
    public const ESCAPING_JS = 'js';
    public const ESCAPING_JSON = 'json';
    public const ESCAPING_MYSQL = 'mysql';
    public const ESCAPING_PGSQL = 'pgsql';
    public const ESCAPING_CHAR_NAMES = 'names'; // NUL, SOH, STX etc.
    public const ESCAPING_ISO2047_SYMBOLS = 'symbols'; // https://en.wikipedia.org/wiki/ISO_2047
    public const ESCAPING_CP437 = 'cp437'; // https://en.wikipedia.org/wiki/Code_page_437

    public const JSON_KEEP_AS_IS = 0;
    public const JSON_PRETTIFY = 1;
    //public const JSON_HIGHLIGHT = 2;
    //public const JSON_PRETTIFY_AND_HIGHLIGHT = 3;
    public const JSON_DECODE = 4;

    public const ORDER_ORIGINAL = 1;
    public const ORDER_ALPHABETIC = 2;
    public const ORDER_VISIBILITY_ALPHABETIC = 3;

    public const FILTER_UNINITIALIZED = 1;
    public const FILTER_NULLS = 2;
    public const FILTER_EMPTY_STRINGS = 4;
    public const FILTER_EMPTY_ARRAYS = 8;

    /** @var DumperConfig */
    public static $config;

    /** @var array<string, string|null> - configuration of colors for dumped types and special characters */
    public static $colors = [
        'null' => Ansi::WHITE, // null
        'bool' => Ansi::WHITE, // true, false
        'int' => Ansi::LRED, // 123
        'float' => Ansi::LRED, // 123.4

        'value' => Ansi::LYELLOW, // primary color for formatted internal value of an object
        'value2' => Ansi::DYELLOW, // secondary color for formatted internal value of an object

        'string' => Ansi::LCYAN, // "foo"
        'escape_basic' => Ansi::DCYAN, // basic escaped characters (whitespace and quotes)
        'escape_special' => Ansi::LMAGENTA, // special ascii characters (without whitespace)
        'escape_non_ascii' => Ansi::DMAGENTA, // characters outside ascii (\x80-\xff)

        'resource' => Ansi::LRED, // stream
        'namespace' => Ansi::DCYAN, // Foo...
        'backslash' => Ansi::DGRAY, // // ...\...
        'class' => Ansi::DCYAN, // ...Bar
        'access' => Ansi::DGRAY, // public private protected
        'constant' => Ansi::DCYAN, // FOO
        'case' => Ansi::DCYAN, // enum case name
        'property' => Ansi::DYELLOW, // $foo
        'function' => Ansi::DCYAN, // function/method name
        'key' => Ansi::WHITE, // array keys. set null to use string/int formats

        'closure' => Ansi::LGRAY, // static function ($a) use ($b)
        'parameter' => Ansi::DYELLOW, // $a, $b
        'type' => Ansi::WHITE, // int
        'operator' => Ansi::LGRAY, // | &
        'reference' => Ansi::LRED, // &
        'bracket' => Ansi::WHITE, // [ ] { } ( )
        'symbol' => Ansi::LGRAY, // , ; :: : => =
        'doc' => Ansi::DGRAY, // /** ... */
        'annotation' => Ansi::LGRAY, // @param
        'indent' => Ansi::DGRAY, // |
        'info' => Ansi::DGRAY, // // 5 items

        'table' => Ansi::LGREEN,
        //'column' => Ansi::LYELLOW,

        'path' => Ansi::DGRAY, // C:/foo/bar/...
        'file' => Ansi::LGRAY, // .../baz.php
        'line' => Ansi::DGRAY, // :42

        'errors' => Ansi::LRED, // failed SQL etc.
        'exceptions' => Ansi::LMAGENTA, // RECURSION, SKIPPED, *****, ... (max depth, max length, not traversed)

        'call' => Ansi::DCYAN, // intercept or stream wrapper function call
        'time' => Ansi::LBLUE, // operation time
        'memory' => Ansi::DBLUE, // allocated memory
    ];

    // type formatter settings -----------------------------------------------------------------------------------------

    /** @var bool - turn on/of user formatters for dumps */
    public static $useFormatters = true;

    /** @var array<int|string, callable(int, DumperConfig): ?string> - user formatters for int values. optionally indexed by key regexp */
    public static $intFormatters = [
        '~stdClass::charsetnr~' => [FormattersDefault::class, 'dumpIntMysqlCharset'],
        '~stdClass::type~' => [FormattersDefault::class, 'dumpIntMysqlType'],
        '~stdClass::flags~' => [FormattersDefault::class, 'dumpIntMysqlFlags'],
        '~filemode|permissions~i' => [FormattersDefault::class, 'dumpIntPermissions'],
        '~termsig|stopsig|signal~i' => [FormattersDefault::class, 'dumpIntSignal'],
        '~exit~i' => [FormattersDefault::class, 'dumpIntExitCode'],
        '~time|until|since|\\Wts~i' => [FormattersDefault::class, 'dumpIntTime'],
        '~size|bytes|memory~i' => [FormattersDefault::class, 'dumpIntSize'],
        '~flags|options|headeropt|settings~i' => [FormattersDefault::class, 'dumpIntFlags'],
        '~(http|response)_?(code|status)~i' => [FormattersDefault::class, 'dumpIntHttpCode'],
        [FormattersDefault::class, 'dumpIntPowersOfTwo'],
    ];

    /** @var array<int|string, callable(float, DumperConfig): ?string> - user formatters for float values. optionally indexed by key regexp */
    public static $floatFormatters = [
        '~time|until|since~i' => [FormattersDefault::class, 'dumpFloatTime'],
    ];

    /** @var array<int|string, callable(string, DumperConfig): ?string> - user formatters for string values. optionally indexed by key regexp */
    public static $stringFormatters = [
        [FormattersDefault::class, 'dumpStringHidden'], // must be first!
        '/path(?!ext)/i' => [FormattersDefault::class, 'dumpStringPathList'],
        [FormattersDefault::class, 'dumpStringPath'],
        [FormattersDefault::class, 'dumpStringKeyValuePair'],
        [FormattersDefault::class, 'dumpStringUuid'],
        [FormattersDefault::class, 'dumpStringColor'],
        [FormattersDefault::class, 'dumpStringCallable'],
        [FormattersDefault::class, 'dumpStringJson'],
    ];

    /** @var array<string, callable(int|string, DumperConfig): ?string> - user formatters for array keys. indexed by regexp matching the key containing the array - either "Class::$property" or "function.parameter. returns key info */
    public static $arrayKeyFormatters = [
        'curl_setopt_array.1' => [CurlInterceptor::class, 'getCurlSetoptArrayKeyInfo'],
    ];

    /** @var array<string, callable(resource, DumperConfig): string> - user formatters for resources */
    public static $resourceFormatters = [
        '(stream)' => [FormattersDefault::class, 'dumpStream'],
        '(stream-context)' => [FormattersDefault::class, 'dumpStreamContext'],
        '(process)' => [FormattersDefault::class, 'dumpProcess'],
        '(closed)' => [FormattersDefault::class, 'dumpClosedProcess'],
    ];

    /** @var array<class-string, callable(object, DumperConfig): ?string> - user formatters for dumping objects and resources */
    public static $objectFormatters = [
        // native classes
        BackedEnum::class => [FormattersDefault::class, 'dumpBackedEnum'],
        UnitEnum::class => [FormattersDefault::class, 'dumpUnitEnum'],
        WeakReference::class => [FormattersDefault::class, 'dumpWeakReference'],
        OuterIterator::class => [FormattersDefault::class, 'dumpOuterIterator'],
        IteratorIterator::class => [FormattersDefault::class, 'dumpOuterIterator'],
        DateTimeInterface::class => [FormattersDefault::class, 'dumpDateTimeInterface'],
        mysqli::class => [FormattersDefault::class, 'dumpMysqli'],
        mysqli_stmt::class => [FormattersDefault::class, 'dumpMysqliStatement'],
        mysqli_result::class => [FormattersDefault::class, 'dumpMysqliResult'],

        // Debug
        Callstack::class => [FormattersDefault::class, 'dumpCallstack'],
    ];

    /** @var array<class-string|int, callable(object, DumperConfig): ?string> - user formatters for dumping objects and resources in single-line mode */
    public static $shortObjectFormatters = [
        [FormattersDefault::class, 'dumpEntityId'],
    ];

    /** @var array<string> - classes, methods ("Class::method") and ("Class::$property") that are not traversed. short dumps are used if configured */
    public static $doNotTraverse = [];

    // internals -------------------------------------------------------------------------------------------------------

    /** @var array<string, bool> */
    private static $objects = [];

    /**
     * var_dump() with output capture and better formatting
     *
     * @param mixed $value
     * @param mixed $args arguments to update default DumperConfig from self::$config for this dump or a DumperConfig itself
     */
    public static function varDump($value, ...$args): string
    {
        $config = self::$config->update($args);

        ob_start();
        var_dump($value);
        $dump = ob_get_clean();

        if ($dump === false) {
            $message = Ansi::white(" Output buffer closed unexpectedly in Dumper::varDump(). ", Ansi::DRED);
            Debugger::send(Message::ERROR, $message);

            return '';
        }

        $dump = str_replace(']=>', '] =>', $dump);
        $dump = preg_replace('~=>\n\s+~', '=> ', $dump);
        $dump = preg_replace('~{\n\s+}~', '{ }', $dump);

        if ($config->colors) {
            $dump = Str::replaceKeys($dump, [
                '*RECURSION*' => self::exceptions('recursion'),
                '=> NULL' => '=> ' . self::null('null'),
                '=> bool(true)' => '=> ' . self::bool('true'),
                '=> bool(false)' => '=> ' . self::bool('false'),
            ]);

            // classes & properties
            $dump = preg_replace_callback('~class ([a-zA-Z0-9\\\\_]+)#~', static function (array $match) use ($config): string {
                return 'class ' . self::class($match[1], $config) . '#';
            }, $dump);
            $dump = preg_replace_callback('~(public|private|protected) (\\$[a-zA-Z0-9_]+) =>~', static function (array $match): string {
                return $match[1] . ' ' . self::property($match[2]) . ' =>';
            }, $dump);

            // keys
            $dump = preg_replace_callback('~\["(.*)"]~', static function (array $match) use ($config): string {
                return self::string($match[1], $config);
            }, $dump);
            $dump = preg_replace_callback('~\[(\d+)]~', static function (array $match) use ($config): string {
                return self::int($match[1], $config);
            }, $dump);

            // values
            $dump = preg_replace_callback('~(string\(\d+\) )"(.*)"~', static function (array $match) use ($config): string {
                return self::dumpString($match[2], $config);
            }, $dump);
            $dump = preg_replace_callback('~int\((\d+)\)~', static function (array $match) use ($config): string {
                return self::dumpInt((int) $match[1], $config);
            }, $dump);
            $dump = preg_replace_callback('~float\((.*)\)~', static function (array $match) use ($config): string {
                return self::dumpFloat((float) $match[1], $config);
            }, $dump);
        } else {
            $dump = preg_replace_callback('~\["(.*)"]~', static function (array $match) {
                return $match[1];
            }, $dump);
            $dump = preg_replace_callback('~\[(\d+)]~', static function (array $match) {
                return $match[1];
            }, $dump);
        }

        return trim($dump);
    }

    /**
     * Dump value preceded by expression it came from and followed by trace
     *
     * @param mixed $value
     * @param mixed $args arguments to update default DumperConfig from self::$config for this dump or a DumperConfig itself
     */
    public static function dump($value, ...$args): string
    {
        self::$objects = [];
        $config = self::$config->update($args);

        $callstack = Callstack::get($config->traceFilters);
        $expression = $name ?? ($config->dumpExpressions ? self::findExpression($callstack, $config) : null);

        $result = self::dumpValue($value, $config, 0, $expression === true ? null : $expression);

        $callstack = $callstack->filter($config->traceFilters);
        $trace = self::formatCallstack($callstack, $config, $config->traceLength, 0, 0);

        // mark ordinary arrays as literals
        if ($expression !== null && $expression !== true && $expression[0] === '[' && !is_callable($value)) {
            $expression = true;
        }
        $exp = '';
        if ($config->dumpExpressions) {
            if ($expression === true) {
                $exp = self::key('literal', $config, true) . self::symbol(':') . ' ';
            } elseif ($expression === null) {
                $exp = self::key('unknown', $config, true) . self::symbol(':') . ' ';
            } else {
                $exp = self::key($expression, $config, true) . self::symbol(':') . ' ';
            }
        }

        return $exp . $result . ($trace ? "\n" : '') . $trace;
    }

    /**
     * @internal call only from formatters
     * @param mixed $value
     * @param string|int|null $key
     */
    public static function dumpValue($value, DumperConfig $config, int $depth, $key = null): string
    {
        if ($depth === 0) {
            self::$objects = [];
        }

        if (in_array($key, $config->hiddenFields, true)) {
            return self::exceptions('*****');
        }

        try {
            if ($value === null) {
                return self::null('null');
            } elseif ($value === true) {
                return self::bool('true');
            } elseif ($value === false) {
                return self::bool('false');
            } elseif (is_int($value)) {
                return self::dumpInt($value, $config, (string) $key);
            } elseif (is_float($value)) {
                return self::dumpFloat($value, $config, (string) $key);
            } elseif (is_string($value)) {
                if ($depth === 0 && is_string($key) && str_ends_with($key, '::class')) {
                    return self::dumpClass($value, $config, $depth);
                } else {
                    return self::dumpString($value, $config, $depth, (string) $key);
                }
            } elseif (is_array($value)) {
                // dump as callable when called as `dump([$foo, 'bar'])`
                if ($depth === 0 && count($value) === 2 && is_string($key) && $key[0] === '[' && is_callable($value)) {
                    return self::dumpMethod($value, $config, $depth);
                } else {
                    return self::dumpArray($value, $config, $depth, (string) $key);
                }
            } elseif (is_object($value)) {
                if ($value instanceof Closure) {
                    return self::dumpClosure($value, $config, $depth);
                } else {
                    return self::dumpObject($value, $config, $depth);
                }
            } elseif (is_resource($value)
                || gettype($value) === 'resource (closed)' // 7.4
                || gettype($value) === 'unknown type' // 7.1
            ) {
                return self::dumpResource($value, $config, $depth);
            } else {
                throw new LogicException('Unknown type: ' . gettype($value));
            }
        } catch (LogicException $e) {
            throw $e;
        } catch (Throwable $e) {
            return self::exceptions('cannot dump: ' . get_class($e) . ' - ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * @internal call only from formatters
     */
    public static function dumpInt(int $int, DumperConfig $config, string $key = ''): string
    {
        if ($config->showInfo) {
            if ($int === PHP_INT_MAX) {
                return self::int((string) $int, $config) . ' ' . self::info('// PHP_INT_MAX');
            } elseif ($int === PHP_INT_MIN) {
                return self::int((string) $int, $config) . ' ' . self::info('// PHP_INT_MIN');
            }

            foreach (self::$intFormatters as $pattern => $formatter) {
                if (is_int($pattern) || preg_match($pattern, $key)) {
                    $res = $formatter($int);
                    if ($res !== null) {
                        return $res;
                    }
                }
            }
        }

        return self::int((string) $int, $config);
    }

    /**
     * @internal call only from formatters
     */
    public static function dumpFloat(float $float, DumperConfig $config, string $key = ''): string
    {
        if ($config->showInfo) {
            foreach (self::$floatFormatters as $pattern => $formatter) {
                if (is_int($pattern) || preg_match($pattern, $key)) {
                    $res = $formatter($float);
                    if ($res !== null) {
                        return $res;
                    }
                }
            }
        }

        return self::float($float, $config);
    }

    /**
     * @internal call only from formatters
     */
    public static function dumpString(string $string, DumperConfig $config, int $depth = 0, string $key = ''): string
    {
        $size = strlen($string);
        $length = Str::length($string, $config->inputEncoding);
        $bytes = $size !== $length || $size >= $config->lengthInfoMin ? "{$size} B" : '';
        $chars = $size !== $length ? ", {$length} ch" : '';
        $trimmed = '';
        if ($length > $config->maxLength) {
            $string = Str::substring($string, 0, $config->maxLength, $config->inputEncoding);
            $trimmed = $bytes || $chars ? ', trimmed' : 'trimmed';
        }
        $info = $config->showInfo ? $bytes . $chars . $trimmed : '';

        if ($config->showInfo) {
            foreach (self::$stringFormatters as $pattern => $formatter) {
                if (is_int($pattern) || preg_match($pattern, $key)) {
                    $res = $formatter($string, $config, $info, $key, $depth);
                    if ($res !== null) {
                        return $res;
                    }
                }
            }
        }

        return self::string($string, $config, $depth) . ($info ? ' ' . self::info('// ' . $info) : '');
    }

    /**
     * @internal call only from formatters
     * @param mixed[] $array
     */
    public static function dumpArray(array $array, DumperConfig $config, int $depth = 0, string $key = ''): string
    {
        static $marker;
        if ($marker === null) {
            $marker = uniqid("\0", true);
        }

        if ($array === []) {
            return self::bracket('[]');
        }

        $count = count($array);
        if (isset($array[$marker])) {
            $cnt = Units::units($count, 'item');
            $info = $config->showInfo ? ' ' . self::info("// {$cnt}") : '';

            return self::bracket('[') . ' ' . self::exceptions('recursion') . ' ' . self::bracket(']') . $info;
        }

        // try to speculatively format the array to check if they can fit on one row, even when depth limit is reached
        $over = $depth - $config->maxDepth;
        $short = ''; // only to satisfy PHPStan
        if ($over >= 0) {
            $cnt = Units::units($count, 'item');
            $info = $config->showInfo ? ' ' . self::info("// {$cnt}") : '';
            $short = self::bracket('[') . ' ' . self::exceptions('...') . ' ' . self::bracket(']') . $info;

            if ($over >= 2) {
                // stop speculative descent on MAX + 2 levels
                return $short;
            }
        }

        $long = null;
        do {
            $isList = range(0, $count - 1) === array_keys($array);
            $coma = self::symbol(',');
            $infoPrefix = self::infoPrefix();

            $hasInfo = false;
            $items = [];
            try {
                $array[$marker] = true;
                $n = 0;
                foreach ($array as $k => $value) {
                    if (++$n > $config->arrayMaxLength) {
                        $items[] = self::exceptions('...');
                        break;
                    }
                    if ($k === $marker) {
                        continue;
                    }
                    $dumpedValue = self::dumpValue($value, $config, $depth + 1, $k);
                    $item = !$isList || $config->alwaysShowArrayKeys
                        ? ($count > 10 && is_int($k) && $k >= 0 && $k < 10 ? ' ' : '') . self::key($k, $config) . ' ' . self::symbol('=>') . ' ' . $dumpedValue
                        : $dumpedValue;

                    $pos = strrpos($item, $infoPrefix);
                    $keyInfo = isset(self::$arrayKeyFormatters[$key]) ? self::$arrayKeyFormatters[$key]($k) : '';
                    if ($pos !== false && !strpos(substr($item, $pos), "\n")) {
                        $item = substr($item, 0, $pos) . $coma . substr($item, $pos);
                        if ($keyInfo !== '') {
                            $item = str_replace($infoPrefix, $infoPrefix . $keyInfo . ' => ', $item);
                        }
                        $hasInfo = true;
                    } else {
                        $item .= $coma;
                        if ($keyInfo !== '') {
                            $item .= ' ' . self::info('// ' . $keyInfo);
                        }
                    }

                    $items[] = $item;
                    if ($over >= 0 && strlen($item) > $config->shortArrayMaxLength) {
                        // stop speculative descent on too long item
                        break;
                    }
                }
            } finally {
                unset($array[$marker]);
            }

            $cnt = Units::units($count, 'item');
            $info = $config->showInfo ? ' ' . self::info("// {$cnt}") : '';
            $start = self::bracket('[');
            $end = self::bracket(']') . $info;

            $length = Ansi::length(implode(', ', $items), $config->inputEncoding);

            if ($over >= 0 && $length > $config->shortArrayMaxLength) {
                // stop speculative descent on too long output
                break;
            }

            if ($isList && $length < $config->shortArrayMaxLength && !$hasInfo) {
                // simple values: "[1, 2, 3] // 3 items"
                $items = array_map('ltrim', $items);
                $long = $start . substr(implode(' ', $items), 0, -strlen($coma)) . $end;
            } elseif ($isList && $length < $config->shortArrayMaxLength && $count < $config->shortArrayMaxItems) {
                // squish lines: "['foo', 'bar'] // 2 items (3 B, 3 B)"
                $items = array_map('ltrim', $items);
                $values = [];
                $infos = [];
                foreach ($items as $item) {
                    $parts = explode($infoPrefix, $item);
                    $parts[] = '';
                    [$v, $i] = $parts;
                    $i = Ansi::removeColors($i);
                    $values[] = $v;
                    $infos[] = $i;
                }
                $infos = array_filter($infos);

                $long = $start . substr(implode(' ', $values), 0, -strlen($coma))
                    . $end . self::info(' (' . implode(', ', $infos)) . self::info(')');
            } else {
                // item per line
                $indent = self::indent($depth, $config);
                $indent2 = self::indent($depth + 1, $config);

                $long = $start . "\n" . $indent2 . implode("\n" . $indent2, $items) . "\n" . $indent . $end;
            }
        } while (false); // @phpstan-ignore-line

        return $long ?? $short;
    }

    /**
     * @internal call only from formatters
     * @param object $object
     * @param self::FILTER_*
     */
    public static function dumpObject($object, DumperConfig $config, int $depth = 0, ?int $filter = null): string
    {
        $hash = spl_object_hash($object);
        $recursion = self::$objects[$hash] ?? null;
        $class = get_class($object);

        if ($recursion === true && !$filter) {
            $short = self::dumpObjectShort($object, $config, $hash);
            if ($short) {
                return $short;
            } else {
                return self::class($class, $config) . ' ' . self::bracket('{') . ' '
                    . self::exceptions('recurrence of #' . self::objectHash($object)) . ' ' . self::bracket('}');
            }
        } elseif (is_string($recursion)) {
            return $recursion;
        }
        if ($recursion === null) {
            self::$objects[$hash] = true;
        }

        $info = self::objectInfo($object, $config);

        $handlerResult = '';
        if (self::$useFormatters && $depth < $config->maxDepth + 1 && !$filter) {
            $handler = self::$objectFormatters[$class] ?? null;
            if ($handler !== null) {
                $handlerResult = $handler($object, $config, $depth);
            }
            if ($handlerResult === null || $handlerResult === '') {
                foreach (self::$objectFormatters as $cl => $handler) {
                    if (is_a($object, $cl) && $cl !== $class) {
                        $handlerResult = $handler($object, $config, $depth);
                        if ($handlerResult !== null) {
                            break;
                        }
                    }
                }
            }
        }

        $skip = in_array($class, self::$doNotTraverse, true) && $depth !== 0;
        if ($depth >= $config->maxDepth || $skip) {
            if ($handlerResult !== '' && !str_contains($handlerResult, "\n")) {
                self::$objects[$hash] = $handlerResult;
                return $handlerResult;
            }

            $short = self::dumpObjectShort($object, $config, $hash);
            if ($short) {
                return $short;
            }

            return self::class($class, $config) . ' ' . self::bracket('{') . ' '
                . self::exceptions('...') . ' ' . self::bracket('}') . $info;
        }

        if ($handlerResult !== '') {
            if (!str_contains($handlerResult, "\n")) {
                self::$objects[$hash] = $handlerResult;
            }
            return $handlerResult;
        }

        $properties = self::dumpProperties((array) $object, $config, $depth, $class, $filter);
        $filtered = $filter ? ' ' . self::exceptions('filtered') : '';
        if ($properties !== '') {
            return self::class($class, $config) . $filtered . ' ' . self::bracket('{') . $info
                . "\n" . $properties . "\n" . self::indent($depth, $config) . self::bracket('}');
        } else {
            return self::class($class, $config) . $filtered . ' ' . self::bracket('{') . ' ' . self::bracket('}') . $info;
        }
    }

    /**
     * @internal call only from formatters
     * @param object $object
     */
    private static function dumpObjectShort($object, DumperConfig $config, string $hash): ?string
    {
        /** @var int|class-string $cl */
        foreach (self::$shortObjectFormatters as $cl => $handler) {
            if (is_int($cl) || is_a($object, $cl)) {
                $short = $handler($object, $config);
                if ($short) {
                    self::$objects[$hash] = $short;
                    return $short;
                }
            }
        }

        return null;
    }

    /**
     * @internal call only from formatters
     * @param mixed[] $properties
     * @param class-string $class
     */
    public static function dumpProperties(array $properties, DumperConfig $config, int $depth, string $class, ?int $filter = null): string
    {
        $indent = self::indent($depth + 1, $config);
        $equal = ' ' . self::symbol('=') . ' ';
        $semi = self::symbol(';');
        $infoPrefix = self::infoPrefix();

        $uninitialized = new class() {

        };
        if (PHP_VERSION_ID >= 70400 && $config->showUninitializedProperties) {
            $propRefs = self::collectProperties($class);
            if (count($properties) !== count($propRefs)) {
                foreach ($propRefs as $key => $propRef) {
                    if (!array_key_exists($key, $properties)) {
                        $properties[$key] = $uninitialized;
                    }
                }
            }
        }

        $n = 0;
        $items = [];
        $nulls = [];
        $empty = [];
        foreach ($properties as $key => $value) {
            if (($filter & self::FILTER_UNINITIALIZED) !== 0 && $value === $uninitialized) {
                continue;
            } elseif (($filter & self::FILTER_NULLS) !== 0 && $value === null) {
                continue;
            } elseif (($filter & self::FILTER_EMPTY_STRINGS) !== 0 && $value === '') {
                continue;
            } elseif (($filter & self::FILTER_EMPTY_ARRAYS) !== 0 && $value === []) {
                continue;
            }

            $parts = explode("\0", $key);
            if (count($parts) === 4) { // \0Class@anonymous\0path:line$foo\0var
                $name = $parts[3];
                $cls = $parts[1] . "\x00" . $parts[2];
            } elseif (count($parts) === 3) { // \0Class\0var
                $name = $parts[2];
                $cls = $parts[1];
            } else { // var
                $name = $parts[0];
                $cls = null;
            }
            $access = self::access($cls === '*' ? 'protected' : ($cls === null ? 'public' : 'private'));
            $doNotTraverse = in_array($class . '::$' . $name, self::$doNotTraverse, true) ? 1000 : 0;
            $valueDump = $value === $uninitialized
                ? self::exceptions('uninitialized')
                : self::dumpValue($value, $config, $depth + 1 + $doNotTraverse, $class . '::' . $name);

            if ($config->groupNullAndUninitialized && $value === null) {
                $nulls[] = $cls === null || $cls === '*' || $cls === $class
                    ? self::info('$' . $name)
                    : self::info($cls) . self::info('::$' . $name);
                continue;
            } elseif ($config->groupNullAndUninitialized && $value === $uninitialized) {
                $empty[] = $cls === null || $cls === '*' || $cls === $class
                    ? self::info('$' . $name)
                    : self::info($cls) . self::info('::$' . $name);
                continue;
            }

            $fullName = $cls === null || $cls === '*' || $cls === $class
                ? self::property('$' . $name)
                : self::class($cls, $config) . '::' . self::property('$' . $name);

            $item = $indent . $access . ' ' . $fullName . $equal . $valueDump;

            $pos = strrpos($item, $infoPrefix);
            if ($pos !== false && !strpos(substr($item, $pos), "\n")) {
                $item = substr($item, 0, $pos) . $semi . substr($item, $pos);
            } else {
                $item .= $semi;
            }

            $k = $config->propertyOrder === self::ORDER_VISIBILITY_ALPHABETIC
                ? $access . ' ' . $name
                : ($config->propertyOrder === self::ORDER_ALPHABETIC ? $name : $n);

            $items[$k] = $item;
            $n++;
        }

        ksort($items);

        if ($nulls !== []) {
            sort($nulls);
            $items[] = $indent . implode(', ', $nulls) . $equal . self::null('null') . $semi;
        }
        if ($empty !== []) {
            sort($empty);
            $items[] = $indent . implode(', ', $empty) . $equal . self::exceptions('uninitialized') . $semi;
        }

        return implode("\n", $items);
    }

    /**
     * @internal call only from formatters
     * @param class-string $class
     * @return array<string, ReflectionProperty>
     */
    private static function collectProperties(string $class): array
    {
        $propRefs = [];
        $ref = new ReflectionClass($class);
        do {
            $propRefs[] = $ref->getProperties();
            foreach ($ref->getTraits() as $trait) {
                $propRefs[] = $trait->getProperties();
            }
            $ref = $ref->getParentClass();
        } while ($ref !== false);

        $propRefs = array_merge([], ...$propRefs);

        $indexed = [];
        foreach ($propRefs as $propRef) {
            if ($propRef->isPrivate()) {
                $key = "\0" . $propRef->getDeclaringClass()->getName() . "\0" . $propRef->name;
            } elseif ($propRef->isProtected()) {
                $key = "\0*\0" . $propRef->name;
            } else {
                $key = $propRef->name;
            }
            $indexed[$key] = $propRef;
        }

        return $indexed;
    }

    /**
     * @internal call only from formatters
     * @param class-string $class
     */
    public static function dumpClass(string $class, DumperConfig $config, int $depth = 0): string
    {
        $indent = self::indent(1, $config);
        $equal = ' ' . self::symbol('=') . ' ';
        $semi = self::symbol(';');
        $infoPrefix = self::infoPrefix();

        $n = 0;
        $items = [];
        $ref = new ReflectionClass($class);
        foreach ($ref->getProperties() as $property) {
            if (!$property->isStatic()) {
                continue;
            }
            $access = self::access($property->isPrivate() ? 'private static' : ($property->isProtected() ? 'protected static' : 'public static'));
            $name = self::property('$' . $property->getName());
            $property->setAccessible(true);
            $value = self::dumpValue($property->getValue($ref), $config, $depth + 1, $property->getName());

            $item = $indent . $access . ' ' . $name . $equal . $value;

            $pos = strrpos($item, $infoPrefix);
            if ($pos !== false && str_contains(substr($item, $pos), "\n")) {
                $item = substr($item, 0, $pos) . $semi . substr($item, $pos);
            } else {
                $item .= $semi;
            }

            $k = $config->propertyOrder === self::ORDER_VISIBILITY_ALPHABETIC
                ? $access . ' ' . $name
                : ($config->propertyOrder === self::ORDER_ALPHABETIC ? $name : $n);

            $items[$k] = $item;
            $n++;
        }
        ksort($items);
        $items = implode("\n", $items) . "\n";

        $methods = '';
        if ($config->dumpStaticVariablesOfMethods) {
            $methods = [];
            foreach ($ref->getMethods() as $method) {
                $variables = $method->getStaticVariables();
                if ($variables !== []) {
                    $name = $method->getName();
                    $access = $method->isProtected() ? 'protected' : ($method->isPublic() ? 'public' : 'private');
                    $variables = self::dumpVariables($variables, $config, $depth + 1, true);
                    $methods[$name] = $indent . self::access($access . ($method->isStatic() ? ' static' : '') . ' function ')
                        . self::function($name) . self::bracket('()') . ' ' . self::bracket('{') . $variables . $indent . self::bracket('}');
                }
            }
            ksort($methods);
            $methods = implode("\n", $methods) . "\n";
        }

        return self::class($class, $config) . self::symbol('::') . self::symbol('class') . ' '
            . self::bracket('{') . "\n" . $items . $methods . self::bracket('}');
    }

    /**
     * @internal call only from formatters
     */
    public static function dumpClosure(Closure $closure, DumperConfig $config, int $depth = 0): string
    {
        $ref = new ReflectionFunction($closure);

        $variables = $ref->getStaticVariables();

        $fileName = $ref->getFileName();
        if ($fileName !== false) {
            /** @var int $startLine */
            $startLine = $ref->getStartLine();
            $file = $config->showInfo ? self::info(' // ') . self::fileLine($fileName, $startLine, $config) : '';
            $lines = file($fileName);
        } else {
            // in case of closure over native function, eg: "strlen(...)"
            $file = '';
            $lines = false;
        }

        if ($lines !== false && $lines !== []) {
            $lines = array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1);

            $firstLine = array_shift($lines);
            // spell-check-ignore: im
            /** @var int $start */
            $start = Str::matchPos($firstLine, '~(static\\s+)?(function|fn)\\s+([a-zA-Z0-9_]+\\s*)?\\(~im');
            if ($start === null) {
                // todo: quick fix for closures returning other extent than regular function - detect function header better
                $lines = array_slice($lines, $ref->getStartLine() - 2, $ref->getEndLine() - $ref->getStartLine() + 1);
                $firstLine = array_shift($lines);
                $start = Str::matchPos($firstLine, '~(static\\s+)?(function|fn)\\s+([a-zA-Z0-9_]+\\s*)?\\(~im');
                // rl($firstLine);
            }
            $firstLine = substr($firstLine, $start);
            // in case of Closure:fromCallable() we have a name
            $firstLine = preg_replace_callback('~function(\\s+)([a-zA-Z0-9_]+)(\\s*)\\(~', static function ($m): string {
                return 'function' . $m[1] . self::function($m[2]) . $m[3] . '(';
            }, $firstLine);
            if ($firstLine === null) {
                throw new LogicException('Should not happen');
            }

            array_unshift($lines, $firstLine);
            $lines = array_map(static function (string $line): string {
                return trim($line);
            }, $lines);
            $lines = implode(' ', $lines);

            /** @var int $end */
            $end = strpos($lines, '{') ?: strpos($lines, '=>');
            $head = substr($lines, 0, $end);
        } else {
            $params = [];
            foreach ($ref->getParameters() as $param) {
                $name = '$' . $param->getName();
                // todo: investigate type. but is this ever needed anyway?
                //$type = $param->getType();
                $params[] = $name;
            }
            $params = implode(', ', $params);

            $name = str_contains($ref->getName(), '{closure}')
                ? ''
                : self::function($ref->getName());

            $head = "function {$name}({$params}) ";
            if ($variables !== []) {
                $vars = implode(', ', array_map(static function (string $var): string {
                    return '$' . $var;
                }, array_keys($variables)));
                $head .= "use ({$vars}) ";
            }
        }

        $head = self::class('Closure', $config) . ' ' . self::closure($head) . self::bracket('{');
        $variables = self::dumpVariables($variables, $config, $depth, true);

        return $variables
            ? $head . $file . $variables . self::indent($depth, $config) . self::bracket('}')
            : $head . self::bracket('}') . $file;
    }

    /**
     * @internal call only from formatters
     */
    public static function dumpMethod(callable $callable, DumperConfig $config, int $depth = 0): string
    {
        if (!is_callable($callable)) {
            throw new InvalidArgumentException('Callable array expected.');
        }

        [$object, $method] = $callable;

        if (is_object($object)) {
            $ref = (new ReflectionObject($object))->getMethod($method);
            $name = self::class(get_class($object), $config) . self::symbol('::')
                . self::function($method) . self::bracket('()');
        } else {
            $ref = (new ReflectionClass($object))->getMethod($method);
            $name = self::class($object, $config) . self::symbol('::')
                . self::function($method) . self::bracket('()');
        }

        $variables = self::dumpVariables($ref->getStaticVariables(), $config, $depth, true);

        return $name . ' ' . self::bracket('{') . $variables . self::bracket('}');
    }

    /**
     * @internal call only from formatters
     * @param mixed[] $variables
     */
    public static function dumpVariables(array $variables, DumperConfig $config, int $depth, bool $static = false): string
    {
        if ($variables === []) {
            return '';
        }

        $indent = self::indent($depth + 1, $config);
        $equal = ' ' . self::symbol('=') . ' ';
        $semi = self::symbol(';');
        $infoPrefix = self::infoPrefix();

        $n = 0;
        $items = [];
        foreach ($variables as $name => $value) {
            $var = ($static ? self::access('static') . ' ' : '') . self::property('$' . $name);
            $value = self::dumpValue($value, $config, $depth + 1, $name);

            $item = $indent . $var . $equal . $value;

            $pos = strrpos($item, $infoPrefix);
            if ($pos !== false && !strpos(substr($item, $pos), "\n")) {
                $item = substr($item, 0, $pos) . $semi . substr($item, $pos);
            } else {
                $item .= $semi;
            }

            $k = $config->propertyOrder === self::ORDER_VISIBILITY_ALPHABETIC
                || $config->propertyOrder === self::ORDER_ALPHABETIC ? $name : $n;

            $items[$k] = $item;
            $n++;
        }

        ksort($items);

        return "\n" . implode("\n", $items) . "\n";
    }

    /**
     * @internal call only from formatters
     * @param mixed[] $variables
     */
    public static function dumpArguments(array $variables, DumperConfig $config): string
    {
        if ($variables === []) {
            return '';
        }

        $indent = self::indent(1, $config);
        $sep = self::symbol(':') . ' ';
        $coma = self::symbol(',');
        $infoPrefix = self::infoPrefix();

        $items = [];
        foreach ($variables as $name => $value) {
            $name = (string) $name;
            if ($name[0] !== '$') {
                $name = '$' . $name;
            }
            $var = self::property($name);
            $value = self::dumpValue($value, $config, 1, $name);

            $item = $indent . $var . $sep . $value;

            $pos = strrpos($item, $infoPrefix);
            if ($pos !== false && !strpos(substr($item, $pos), "\n")) {
                $item = substr($item, 0, $pos) . $coma . substr($item, $pos);
            } else {
                $item .= $coma;
            }

            $items[] = $item;
        }

        return implode("\n", $items);
    }

    /**
     * @internal call only from formatters
     * @param resource|int $resource
     */
    public static function dumpResource($resource, DumperConfig $config, int $depth = 0): string
    {
        $type = is_resource($resource) ? get_resource_type($resource) : 'closed';
        $id = (int) $resource;
        $name = "({$type})";

        foreach (self::$resourceFormatters as $class => $handler) {
            if ($class === $name) {
                return $handler($resource, $config, $depth);
            }
        }

        $name = "({$type} {$id})";

        return self::resource($name);
    }

}
