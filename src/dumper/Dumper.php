<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use BackedEnum;
use Closure;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use mysqli;
use ReflectionClass;
use ReflectionFunction;
use ReflectionObject;
use UnitEnum;
use function array_filter;
use function array_keys;
use function array_map;
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

class Dumper
{
    use DumperTraces;
    use DumperFormatters;

    public const ESCAPING_NONE = 0;
    public const ESCAPING_PHP = 1;
    public const ESCAPING_JS = 2;
    public const ESCAPING_JSON = 3;
    public const ESCAPING_MYSQL = 4;
    public const ESCAPING_PGSQL = 5;
    public const ESCAPING_CHAR_NAMES = 6;
    public const ESCAPING_ISO2047_SYMBOLS = 7; // https://en.wikipedia.org/wiki/ISO_2047
    public const ESCAPING_CP437 = 8; // https://en.wikipedia.org/wiki/Code_page_437

    public const ORDER_ORIGINAL = 1;
    public const ORDER_ALPHABETIC = 2;
    public const ORDER_VISIBILITY_ALPHABETIC = 3;

    /** @var bool - prefix dumps with expression being dumped (e.g. "$foo->bar(): [1, 2, 3, 4, 5, 6] // 6 items") */
    public static $dumpExpressions = true;

    /** @var string[] - list of fields that are hidden from dumps */
    public static $hiddenFields = [];

    // scalars settings ------------------------------------------------------------------------------------------------

    /** @var bool|null - render long integers and floats with "_" dividing digits into groups of 3, null for auto on PHP >= 7.4 */
    public static $numbersWithUnderscore = false;

    /** @var int - max length of dumped strings */
    public static $maxLength = 10000;

    /** @var string - encoding of dumped strings (todo: output encoding should be always utf-8) */
    public static $inputEncoding = 'utf-8';

    /** @var int - string escaping for strings without control characters (allowed only \n, \r, \t etc.) */
    public static $stringsEscaping = self::ESCAPING_PHP;

    /** @var int - string escaping for binary strings containing control characters (except \n, \r, \t etc.) */
    public static $binaryEscaping = self::ESCAPING_CP437;

    /** @var int - string escaping for labels and raw output */
    public static $rawEscaping = self::ESCAPING_CP437;

    /** @var bool - whether to escape \n, \r, \t or keep them as they are (not relevant for ESCAPING_CP437) */
    public static $escapeWhiteSpace = true;

    /** @var bool - escape all unicode characters outside ascii (not relevant for ESCAPING_SQL and ESCAPING_CP437) */
    public static $escapeAllNonAscii = false;

    /** @var bool - dump binary strings with hexadecimal representation along */
    public static $binaryWithHexadecimal = true;

    /** @var int|null - length of binary string chunks (rows) */
    public static $binaryChunkLength = 16;

    // array and object settings ---------------------------------------------------------------------------------------

    /** @var int - max depth of dumped structures */
    public static $maxDepth = 3;

    /** @var int - max length or array formatted to a single line */
    public static $shortArrayMaxLength = 100;

    /** @var int - max items in an array to format it on single line */
    public static $shortArrayMaxItems = 20;

    /** @var bool - show array keys even on lists with sequential indexes */
    public static $alwaysShowArrayKeys = false;

    /** @var int - ordering of dumped properties of objects */
    public static $propertyOrder = self::ORDER_VISIBILITY_ALPHABETIC;

    /** @var string[] (regexp $long => replacement $short) - replacements of namespaces for shorter class names in dumps */
    public static $namespaceReplacements = [];

    /** @var bool - dump static variables from methods when dumping static members of class (e.g. `rd(Foo::class)`) */
    public static $dumpClassesWithStaticMethodVariables = false;

    // info settings ---------------------------------------------------------------------------------------------------

    /** @var bool|null - show comments with additional info (readable values, object hashes, hints...) */
    public static $showInfo = true;

    /** @var int - show length of strings from n characters */
    public static $lengthInfoMin = 6;

    /** @var DateTimeZone|string|null - timezone used to format timestamps to readable date/time (php.ini timezone by default) */
    public static $infoTimeZone;

    /** @var bool - calculate total memory size of big structures (even below $maxDepth) */
    //public static $infoSize = false;

    // backtrace settings ----------------------------------------------------------------------------------------------

    /** @var int - number of trace lines below a dump */
    public static $traceLength = 1;

    /** @var bool - show class, method, arguments in backtrace */
    public static $traceDetails = true;

    /** @var bool - show depth of each callstack frame */
    public static $traceNumbered = true;

    /** @var int - depth of dumped arguments of called function in backtrace */
    public static $traceArgsDepth = 0;

    /** @var int - count of lines of code shown for each filtered stack frame */
    public static $traceCodeLines = 5;

    /** @var int - count of stack frames for which code should be shown */
    public static $traceCodeDepth = 1;

    /** @var string[] - functions, classes and methods skipped from backtrace */
    public static $traceFilters = [
        '~^Dogma\\\\Debug\\\\~', // Debugger classes
        '~^(ld|rd|rc|rb|rf|rl|rt)$~', // shortcut functions
        '~^call_user_func(_array)?$~', // call proxies
        '~^forward_static_call(_array)?$~',
        '~^(trigger|user)_error$~', // error proxies
        '~^Composer\\\\Autoload\\\\~', // composer loaders
        '~^loadClass~',
        '~^preg_match$~', // thrown from inside
    ];

    /** @var string[] - common path prefixes to remove from all paths (regexps) */
    public static $trimPathPrefixes = [];

    // type formatter settings -----------------------------------------------------------------------------------------

    /** @var array<string, string|null> - configuration of colors for dumped types and special characters */
    public static $colors = [
        'null' => Ansi::LYELLOW, // null
        'bool' => Ansi::LYELLOW, // true, false
        'int' => Ansi::LYELLOW, // 123
        'float' => Ansi::LYELLOW, // 123.4

        'value' => Ansi::LYELLOW, // primary color for formatted internal value of an object
        'value2' => Ansi::DYELLOW, // secondary color for formatted internal value of an object

        'string' => Ansi::LCYAN, // "foo"
        'escape_basic' => Ansi::DCYAN, // basic escaped characters (whitespace and quotes)
        'escape_special' => Ansi::LMAGENTA, // special ascii characters (without whitespace)
        'escape_non_ascii' => Ansi::DMAGENTA, // characters outside ascii (\x80-\xff)

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
        'symbol' => Ansi::LGRAY, // , ; :: : => =
        'indent' => Ansi::DGRAY, // |
        'info' => Ansi::DGRAY, // // 5 items

        'exceptions' => Ansi::LMAGENTA, // RECURSION, SKIPPED, *****, ... (max depth, max length, not traversed)

        'function' => Ansi::LGREEN, // intercept or stream wrapper function call
        'time' => Ansi::LBLUE, // operation time
        'memory' => Ansi::DBLUE, // allocated memory
    ];

    /** @var bool - turn on/of user formatters for dumps */
    public static $useFormatters = true;

    /** @var array<callable> - user formatters for int values. optionally indexed by key regexp */
    public static $intFormatters = [
        '~filemode|permissions~i' => [self::class, 'dumpIntPermissions'],
        '~time|\\Wts~i' => [self::class, 'dumpIntTime'],
        '~size|bytes|memory~i' => [self::class, 'dumpIntSize'],
        '~flags|options|headeropt|settings~i' => [self::class, 'dumpIntFlags'],
        [self::class, 'dumpIntPowersOfTwo'],
    ];

    /** @var array<callable> - user formatters for float values. optionally indexed by key regexp */
    public static $floatFormatters = [
        '~time~i' => [self::class, 'dumpFloatTime'],
    ];

    /** @var array<callable> - user formatters for string values. optionally indexed by key regexp */
    public static $stringFormatters = [
        [self::class, 'dumpStringHidden'], // must be first!
        '/path(?!ext)/i' => [self::class, 'dumpStringPathList'],
        [self::class, 'dumpStringPath'],
        [self::class, 'dumpStringUuid'],
        [self::class, 'dumpStringColor'],
        [self::class, 'dumpStringCallable'],
    ];

    /** @var array<string, callable> - user formatters for resources */
    public static $resourceFormatters = [
        '(stream)' => [self::class, 'dumpStream'],
        '(stream-context)' => [self::class, 'dumpStreamContext'],
    ];

    /** @var array<class-string, callable> - user formatters for dumping objects and resources */
    public static $objectFormatters = [
        // native classes
        BackedEnum::class => [self::class, 'dumpBackedEnum'],
        UnitEnum::class => [self::class, 'dumpUnitEnum'],
        mysqli::class => [self::class, 'dumpMysqli'],
        DateTimeInterface::class => [self::class, 'dumpDateTimeInterface'],

        // Debug
        Callstack::class => [self::class, 'dumpCallstack'],
    ];

    /** @var array<class-string|int, callable> - user formatters for dumping objects and resources in single-line mode */
    public static $shortObjectFormatters = [
        [self::class, 'dumpEntityId'],
    ];

    /** @var array<class-string> - classes and methods that are not traversed. short dumps are used if configured */
    public static $doNotTraverse = [];

    // internals -------------------------------------------------------------------------------------------------------

    /** @var string[] */
    private static $objects = [];

    /**
     * var_dump() with output capture and better formatting
     *
     * @param mixed $value
     */
    public static function varDump($value, bool $colors = true): string
    {
        ob_start();
        var_dump($value);
        $dump = ob_get_clean();

        if ($dump === false) {
            $message = Ansi::white(" Output buffer closed unexpectedly in Dumper::varDump(). ", Ansi::DRED);
            Debugger::send(Packet::ERROR, $message);

            return '';
        }

        $dump = str_replace(']=>', '] =>', $dump);
        $dump = preg_replace('~=>\n\s+~', '=> ', $dump);
        $dump = preg_replace('~{\n\s+}~', '{ }', $dump);

        if ($colors) {
            $dump = Str::replaceKeys($dump, [
                '*RECURSION*' => self::exceptions('recursion'),
                '=> NULL' => '=> ' . self::null('null'),
                '=> bool(true)' => '=> ' . self::bool('true'),
                '=> bool(false)' => '=> ' . self::bool('false'),
            ]);

            // classes & properties
            $dump = preg_replace_callback('~class ([a-zA-Z0-9\\\\_]+)#~', static function (array $match) {
                return 'class ' . self::name($match[1]) . '#';
            }, $dump);
            $dump = preg_replace_callback('~(public|private|protected) (\\$[a-zA-Z0-9_]+) =>~', static function (array $match) {
                return $match[1] . ' ' . self::property($match[2]) . ' =>';
            }, $dump);

            // keys
            $dump = preg_replace_callback('~\["(.*)"]~', static function (array $match) {
                return self::string($match[1]);
            }, $dump);
            $dump = preg_replace_callback('~\[(\d+)]~', static function (array $match) {
                return self::int($match[1]);
            }, $dump);

            // values
            $dump = preg_replace_callback('~(string\(\d+\) )"(.*)"~', static function (array $match) {
                return self::dumpString($match[2]);
            }, $dump);
            $dump = preg_replace_callback('~int\((\d+)\)~', static function (array $match) {
                return self::dumpInt((int) $match[1]);
            }, $dump);
            $dump = preg_replace_callback('~float\((.*)\)~', static function (array $match) {
                return self::dumpFloat((float) $match[1]);
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
     * @param mixed $value
     */
    public static function dump($value, ?int $maxDepth = null, ?int $traceLength = null): string
    {
        self::$objects = [];

        $oldDepth = self::$maxDepth;
        if ($maxDepth !== null) {
            self::$maxDepth = $maxDepth;
        }
        $traceLengthBefore = self::$traceLength;
        if ($traceLength !== null) {
            self::$traceLength = $traceLength;
        }

        try {
            $callstack = Callstack::get(self::$traceFilters);

            $expression = self::$dumpExpressions ? self::findExpression($callstack) : null;

            $result = self::dumpValue($value, 0, $expression === true ? null : $expression);

            $trace = self::formatCallstack($callstack, $traceLength, 0, 0);

            // mark ordinary arrays as literals
            if ($expression !== null && $expression !== true && $expression[0] === '[' && !is_callable($value)) {
                $expression = true;
            }
            $exp = '';
            if (self::$dumpExpressions) {
                if ($expression === true) {
                    $exp = self::key('literal') . self::symbol(':') . ' ';
                } elseif ($expression === null) {
                    $exp = self::key('unknown') . self::symbol(':') . ' ';
                } else {
                    $exp = self::key($expression) . self::symbol(':') . ' ';
                }
            }

            return $exp . $result . ($trace ? "\n" : '') . $trace;
        } finally {
            self::$traceLength = $traceLengthBefore;
            self::$maxDepth = $oldDepth;
        }
    }

    /**
     * @param mixed $value
     * @param string|int|null $key
     */
    public static function dumpValue($value, int $depth = 0, $key = null): string
    {
        if ($depth === 0) {
            self::$objects = [];
        }

        if (in_array($key, self::$hiddenFields, true)) {
            return self::exceptions('*****');
        }

        if ($value === null) {
            return self::null('null');
        } elseif ($value === true) {
            return self::bool('true');
        } elseif ($value === false) {
            return self::bool('false');
        } elseif (is_int($value)) {
            return self::dumpInt($value, (string) $key);
        } elseif (is_float($value)) {
            return self::dumpFloat($value, (string) $key);
        } elseif (is_string($value)) {
            if ($depth === 0 && is_string($key) && str_ends_with($key, '::class')) {
                return self::dumpClass($value, $depth);
            } else {
                return self::dumpString($value, $depth, (string) $key);
            }
        } elseif (is_array($value)) {
            // dump as callable when called as `dump([$foo, 'bar'])`
            if ($depth === 0 && count($value) === 2 && is_string($key) && $key[0] === '[' && is_callable($value)) {
                return self::dumpMethod($value, $depth);
            } else {
                return self::dumpArray($value, $depth);
            }
        } elseif (is_object($value)) {
            if ($value instanceof Closure) {
                return self::dumpClosure($value, $depth);
            } else {
                return self::dumpObject($value, $depth);
            }
        } elseif (is_resource($value)
            || gettype($value) === 'resource (closed)' // 7.4
            || gettype($value) === 'unknown type' // 7.1
        ) {
            return self::dumpResource($value, $depth);
        } else {
            throw new LogicException('Unknown type: ' . gettype($value));
        }
    }

    public static function dumpInt(int $int, string $key = ''): string
    {
        if (self::$showInfo) {
            if ($int === PHP_INT_MAX) {
                return self::int((string) $int) . ' ' . self::info('// PHP_INT_MAX');
            } elseif ($int === PHP_INT_MIN) {
                return self::int((string) $int) . ' ' . self::info('// PHP_INT_MIN');
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

        return self::int((string) $int);
    }

    public static function dumpFloat(float $float, string $key = ''): string
    {
        if (self::$showInfo) {
            foreach (self::$floatFormatters as $pattern => $formatter) {
                if (is_int($pattern) || preg_match($pattern, $key)) {
                    $res = $formatter($float);
                    if ($res !== null) {
                        return $res;
                    }
                }
            }
        }

        return self::float((string) $float);
    }

    public static function dumpString(string $string, int $depth = 0, string $key = ''): string
    {
        $size = strlen($string);
        $length = Str::length($string, self::$inputEncoding);
        $bytes = $size !== $length || $size >= self::$lengthInfoMin ? "$size B" : '';
        $chars = $size !== $length ? ", $length ch" : '';
        $trimmed = '';
        if ($length > self::$maxLength) {
            $string = Str::trim($string, self::$maxLength, self::$inputEncoding);
            $trimmed = $bytes || $chars ? ', trimmed' : 'trimmed';
        }
        $info = self::$showInfo ? $bytes . $chars . $trimmed : '';

        if (self::$showInfo) {
            foreach (self::$stringFormatters as $pattern => $formatter) {
                if (is_int($pattern) || preg_match($pattern, $key)) {
                    $res = $formatter($string, $info, $key, $depth);
                    if ($res !== null) {
                        return $res;
                    }
                }
            }
        }

        return self::string($string, $depth) . ($info ? ' ' . self::info('// ' . $info) : '');
    }

    /**
     * @param mixed[] $array
     */
    public static function dumpArray(array $array, int $depth = 0): string
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
            $info = self::$showInfo ? ' ' . self::info("// $count item" . ($count > 1 ? 's' : '')) : '';

            return self::bracket('[') . ' ' . self::exceptions('recursion') . ' ' . self::bracket(']') . $info;
        }

        // try to speculatively format the array to check if they can fit on one row, even when depth limit is reached
        $over = $depth - self::$maxDepth;
        $short = ''; // only to satisfy PHPStan
        if ($over >= 0) {
            $info = self::$showInfo ? ' ' . self::info("// $count item" . ($count > 1 ? 's' : '')) : '';
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
            /** @var non-empty-string $infoPrefix */
            $infoPrefix = self::infoPrefix();

            $hasInfo = false;
            $items = [];
            try {
                $array[$marker] = true;
                foreach ($array as $k => $value) {
                    if ($k === $marker) {
                        continue;
                    }
                    $dumpedValue = self::dumpValue($value, $depth + 1, $k);
                    $item = !$isList || self::$alwaysShowArrayKeys
                        ? self::key($k) . ' ' . self::symbol('=>') . ' ' . $dumpedValue
                        : $dumpedValue;

                    $pos = strrpos($item, $infoPrefix);
                    if ($pos !== false && !strpos(substr($item, $pos), "\n")) {
                        $item = substr($item, 0, $pos) . $coma . substr($item, $pos);
                        $hasInfo = true;
                    } else {
                        $item .= $coma;
                    }

                    $items[] = $item;
                    if ($over >= 0 && strlen($item) > self::$shortArrayMaxLength) {
                        // stop speculative descent on too long item
                        break;
                    }
                }
            } finally {
                unset($array[$marker]);
            }

            $info = self::$showInfo ? ' ' . self::info("// $count item" . ($count > 1 ? 's' : '')) : '';
            $start = self::bracket('[');
            $end = self::bracket(']') . $info;

            $length = Ansi::length(implode(', ', $items), self::$inputEncoding);

            if ($over >= 0 && $length > self::$shortArrayMaxLength) {
                // stop speculative descent on too long output
                break;
            }

            if ($isList && $length < self::$shortArrayMaxLength && !$hasInfo) {
                // simple values: "[1, 2, 3] // 3 items"
                $long = $start . substr(implode(' ', $items), 0, -strlen($coma)) . $end;
            } elseif ($isList && $length < self::$shortArrayMaxLength && $count < self::$shortArrayMaxItems) {
                // squish lines: "['foo', 'bar'] // 2 items (3 B, 3 B)"
                $values = [];
                $infos = [];
                foreach ($items as $item) {
                    $parts = explode($infoPrefix, $item);
                    $parts[] = '';
                    [$v, $i] = $parts;
                    //$i = str_replace(Ansi::RESET_FORMAT, '', $i);
                    $values[] = $v;
                    $infos[] = $i;
                }
                $infos = array_filter($infos);

                $long = $start . substr(implode(' ', $values), 0, -strlen($coma))
                    . $end . self::info(' (' . implode(', ', $infos) . ')');
            } else {
                // item per line
                $indent = self::indent($depth);
                $indent2 = self::indent($depth + 1);

                $long = $start . "\n" . $indent2 . implode("\n" . $indent2, $items) . "\n" . $indent . $end;
            }
        } while (false); // @phpstan-ignore-line

        return $long ?? $short;
    }

    /**
     * @param object $object
     */
    public static function dumpObject($object, int $depth = 0): string
    {
        $hash = spl_object_hash($object);
        $recursion = isset(self::$objects[$hash]);
        self::$objects[$hash] = true;
        $class = get_class($object);

        if ($recursion) {
            return self::name($class) . ' ' . self::bracket('{') . ' '
                . self::exceptions('recurrence of #' . self::objectHash($object)) . ' ' . self::bracket('}');
        }

        $info = self::objectInfo($object);

        $handlerResult = '';
        if (self::$useFormatters && $depth < self::$maxDepth + 1) {
            $class = get_class($object);
            $handler = self::$objectFormatters[$class] ?? null;
            if ($handler !== null) {
                $handlerResult = $handler($object);
            }

            foreach (self::$objectFormatters as $cl => $handler) {
                if (is_a($object, $cl)) {
                    $handlerResult = $handler($object, $depth);
                    break;
                }
            }
        }

        $skip = in_array($class, self::$doNotTraverse, true);
        if ($depth >= self::$maxDepth || $skip) {
            if ($handlerResult !== '' && !str_contains($handlerResult, "\n")) {
                return $handlerResult;
            }

            /** @var int|class-string $cl */
            foreach (self::$shortObjectFormatters as $cl => $handler) {
                if (is_int($cl) || is_a($object, $cl)) {
                    $short = $handler($object);
                    if ($short) {
                        return $short;
                    }
                }
            }

            return self::name($class) . ' ' . self::bracket('{') . ' '
                . self::exceptions('...') . ' ' . self::bracket('}') . $info;
        }

        if ($handlerResult !== '') {
            return $handlerResult;
        }

        $properties = self::dumpProperties((array) $object, $depth, $class);
        if ($properties !== '') {
            return self::name($class) . ' ' . self::bracket('{') . $info
                . "\n" . $properties . "\n" . self::indent($depth) . self::bracket('}');
        } else {
            return self::name($class) . ' ' . self::bracket('{') . ' ' . self::bracket('}') . $info;
        }
    }

    /**
     * @param mixed[] $properties
     */
    public static function dumpProperties(array $properties, int $depth, string $class): string
    {
        $indent = self::indent($depth + 1);
        $equal = ' ' . self::symbol('=') . ' ';
        $semi = self::symbol(';');
        $infoPrefix = self::infoPrefix();

        $n = 0;
        $items = [];
        foreach ($properties as $name => $value) {
            $parts = explode("\0", $name);
            if (count($parts) === 3) {
                $name = $parts[2];
                $cls = $parts[1];
            } else {
                $name = $parts[0];
                $cls = null;
            }
            $access = self::access($cls === '*' ? 'protected' : ($cls === null ? 'public' : 'private'));
            $value = self::dumpValue($value, $depth + 1, $name);
            $fullName = $cls === null || $cls === '*' || $cls === $class
                ? self::property('$' . $name)
                : self::name($cls) . self::property('::$' . $name);

            $item = $indent . $access . ' ' . $fullName . $equal . $value;

            $pos = strrpos($item, $infoPrefix);
            if ($pos !== false && !strpos(substr($item, $pos), "\n")) {
                $item = substr($item, 0, $pos) . $semi . substr($item, $pos);
            } else {
                $item .= $semi;
            }

            $k = self::$propertyOrder === self::ORDER_VISIBILITY_ALPHABETIC
                ? $access . ' ' . $name
                : (self::$propertyOrder === self::ORDER_ALPHABETIC ? $name : $n);

            $items[$k] = $item;
            $n++;
        }

        ksort($items);

        return implode("\n", $items);
    }

    /**
     * @param class-string $class
     */
    public static function dumpClass(string $class, int $depth = 0): string
    {
        $indent = self::indent(1);
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
            $value = self::dumpValue($property->getValue($ref), $depth + 1, $property->getName());

            $item = $indent . $access . ' ' . $name . $equal . $value;

            $pos = strrpos($item, $infoPrefix);
            if ($pos !== false && str_contains(substr($item, $pos), "\n")) {
                $item = substr($item, 0, $pos) . $semi . substr($item, $pos);
            } else {
                $item .= $semi;
            }

            $k = self::$propertyOrder === self::ORDER_VISIBILITY_ALPHABETIC
                ? $access . ' ' . $name
                : (self::$propertyOrder === self::ORDER_ALPHABETIC ? $name : $n);

            $items[$k] = $item;
            $n++;
        }
        ksort($items);
        $items = implode("\n", $items) . "\n";

        $methods = '';
        if (self::$dumpClassesWithStaticMethodVariables) {
            $methods = [];
            foreach ($ref->getMethods() as $method) {
                $variables = $method->getStaticVariables();
                if ($variables !== []) {
                    $name = $method->getName();
                    $access = $method->isProtected() ? 'protected' : ($method->isPublic() ? 'public' : 'private');
                    $variables = self::dumpVariables($variables, $depth + 1, true);
                    $methods[$name] = $indent . self::access($access . ($method->isStatic() ? ' static' : '') . ' function ')
                        . self::name($name) . self::bracket('()') . ' ' . self::bracket('{') . $variables . $indent . self::bracket('}');
                }
            }
            ksort($methods);
            $methods = implode("\n", $methods) . "\n";
        }

        return self::name($class) . self::symbol('::') . self::name('class') . ' '
            . self::bracket('{') . "\n" . $items . $methods . self::bracket('}');
    }

    public static function dumpClosure(Closure $closure, int $depth = 0): string
    {
        $ref = new ReflectionFunction($closure);

        $variables = $ref->getStaticVariables();

        $fileName = $ref->getFileName();
        if ($fileName !== false) {
            /** @var int $startLine */
            $startLine = $ref->getStartLine();
            $file = self::$showInfo ? self::info(' // ') . self::fileLine($fileName, $startLine) : '';
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
            $firstLine = substr($firstLine, $start);
            // in case of Closure:fromCallable() we have a name
            $firstLine = preg_replace_callback('~function(\\s+)([a-zA-Z0-9_]+)(\\s*)\\(~', static function ($m): string {
                return 'function' . $m[1] . self::name($m[2]) . $m[3] . '(';
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
                : self::name($ref->getName());

            $head = "function $name($params) ";
            if ($variables !== []) {
                $vars = implode(', ', array_map(static function (string $var): string {
                    return '$' . $var;
                }, array_keys($variables)));
                $head .= "use ($vars) ";
            }
        }

        $head = self::name('Closure') . ' ' . self::closure($head) . self::bracket('{');
        $variables = self::dumpVariables($variables, $depth, true);

        return $variables
            ? $head . $file . $variables . self::indent($depth) . self::bracket('}')
            : $head . self::bracket('}') . $file;
    }

    public static function dumpMethod(callable $callable, int $depth = 0): string
    {
        if (!is_callable($callable)) {
            throw new InvalidArgumentException('Callable array expected.');
        }

        [$object, $method] = $callable;

        if (is_object($object)) {
            $ref = (new ReflectionObject($object))->getMethod($method);
            $name = self::name(get_class($object)) . self::symbol('::')
                . self::name($method) . self::bracket('()');
        } else {
            $ref = (new ReflectionClass($object))->getMethod($method);
            $name = self::name($object) . self::symbol('::')
                . self::name($method) . self::bracket('()');
        }

        $variables = self::dumpVariables($ref->getStaticVariables(), $depth, true);

        return $name . ' ' . self::bracket('{') . $variables . self::bracket('}');
    }

    /**
     * @param mixed[] $variables
     */
    public static function dumpVariables(array $variables, int $depth, bool $static = false): string
    {
        if ($variables === []) {
            return '';
        }

        $indent = self::indent($depth + 1);
        $equal = ' ' . self::symbol('=') . ' ';
        $semi = self::symbol(';');
        $infoPrefix = self::infoPrefix();

        $n = 0;
        $items = [];
        foreach ($variables as $name => $value) {
            $var = ($static ? self::access('static') . ' ' : '') . self::property('$' . $name);
            $value = self::dumpValue($value, $depth + 1, $name);

            $item = $indent . $var . $equal . $value;

            $pos = strrpos($item, $infoPrefix);
            if ($pos !== false && !strpos(substr($item, $pos), "\n")) {
                $item = substr($item, 0, $pos) . $semi . substr($item, $pos);
            } else {
                $item .= $semi;
            }

            $k = self::$propertyOrder === self::ORDER_VISIBILITY_ALPHABETIC
                || self::$propertyOrder === self::ORDER_ALPHABETIC ? $name : $n;

            $items[$k] = $item;
            $n++;
        }

        ksort($items);

        return "\n" . implode("\n", $items) . "\n";
    }

    /**
     * @param mixed[] $variables
     */
    public static function dumpArguments(array $variables): string
    {
        if ($variables === []) {
            return '';
        }

        $indent = self::indent(1);
        $sep = self::symbol(':') . ' ';
        $coma = self::symbol(',');
        $infoPrefix = self::infoPrefix();

        $n = 0;
        $items = [];
        foreach ($variables as $name => $value) {
            $var = self::property((string) $name);
            $value = self::dumpValue($value, 1, $name);

            $item = $indent . $var . $sep . $value;

            $pos = strrpos($item, $infoPrefix);
            if ($pos !== false && !strpos(substr($item, $pos), "\n")) {
                $item = substr($item, 0, $pos) . $coma . substr($item, $pos);
            } else {
                $item .= $coma;
            }

            $items[] = $item;
            $n++;
        }

        return implode("\n", $items);
    }

    /**
     * @param resource|int $resource
     */
    public static function dumpResource($resource, int $depth = 0): string
    {
        $type = is_resource($resource) ? get_resource_type($resource) : 'closed';
        $id = (int) $resource;
        $name = "($type)";

        foreach (self::$resourceFormatters as $class => $handler) {
            if ($class === $name) {
                return $handler($resource, $depth);
            }
        }

        $name = "($type $id)";

        return self::resource($name);
    }

}
