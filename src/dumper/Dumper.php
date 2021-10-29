<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Closure;
use Consistence\Enum\Enum;
use Consistence\Enum\MultiEnum;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Dogma\Dom\Element;
use Dogma\Dom\NodeList;
use Dogma\Enum\IntEnum;
use Dogma\Enum\IntSet;
use Dogma\Enum\StringEnum;
use Dogma\Enum\StringSet;
use Dogma\Math\Interval\IntervalSet;
use Dogma\Math\Interval\ModuloIntervalSet;
use Dogma\Pokeable;
use Dogma\Time\Date;
use Dogma\Time\Interval\DateInterval;
use Dogma\Time\Interval\DateTimeInterval;
use Dogma\Time\Interval\NightInterval;
use Dogma\Time\Interval\TimeInterval;
use Dogma\Time\IntervalData\DateIntervalData;
use Dogma\Time\IntervalData\DateIntervalDataSet;
use Dogma\Time\IntervalData\NightIntervalData;
use Dogma\Time\IntervalData\NightIntervalDataSet;
use Dogma\Time\Time;
use DOMAttr;
use DOMCdataSection;
use DOMComment;
use DOMDocument;
use DOMDocumentFragment;
use DOMDocumentType;
use DOMElement;
use DOMEntity;
use DOMNodeList;
use DOMText;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionObject;
use const PATH_SEPARATOR;
use const PHP_INT_MAX;
use const PHP_INT_MIN;
use function array_keys;
use function array_map;
use function array_reverse;
use function array_shift;
use function array_slice;
use function array_unshift;
use function bin2hex;
use function count;
use function explode;
use function get_class;
use function get_resource_type;
use function gettype;
use function hexdec;
use function implode;
use function in_array;
use function ini_get;
use function is_a;
use function is_array;
use function is_callable;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function is_string;
use function ksort;
use function md5;
use function ob_start;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function spl_object_hash;
use function str_replace;
use function stripos;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use function uniqid;
use function var_dump;

class Dumper
{
    use DumperTraces;
    use DumperFormatters;
    use DumperFormattersConsistence;
    use DumperFormattersDogma;
    use DumperFormattersDom;

    // todo: unicode escaping
    public const UNICODE_ESCAPE_ONLY = 1;
    public const UNICODE_ESCAPE_PHP = 2;
    public const UNICODE_ESCAPE_JS = 3;
    public const UNICODE_ESCAPE_SQL = 4;

    // todo: binary escaping
    public const BINARY_ESCAPE_ONLY = 1;
    public const BINARY_ESCAPE_PHP = 2;
    public const BINARY_ESCAPE_JS = 3;
    public const BINARY_ESCAPE_SQL = 4;
    public const BINARY_AS_CP437 = 5;
    public const BINARY_AS_MOJIBAKE = 6;
    public const BINARY_WITH_HEXADECIMAL = 1024;

    public const ORDER_ORIGINAL = 1;
    public const ORDER_ALPHABETIC = 2;
    public const ORDER_VISIBILITY_ALPHABETIC = 3;

    /** @var bool - prefix dumps with expression being dumped (e.g. "$foo->bar(): [1, 2, 3, 4, 5, 6] // 6 items") */
    public static $dumpExpressions = true;

    /** @var string[] - list of fields that are hidden from dumps */
    public static $hiddenFields = [];

    // string settings -------------------------------------------------------------------------------------------------

    /** @var int - max length of dumped strings */
    public static $maxLength = 10000;

    /** @var string - encoding of dumped strings (output encoding is always utf-8) */
    public static $stringsEncoding = 'utf-8';

    /** @var int - unicode strings escaping/formatting */
    //public static $stringsOutput = self::UNICODE_ESCAPE_ONLY;

    /** @var int - binary strings escaping/formatting */
    //public static $binaryOutput = self::BINARY_ESCAPE_ONLY;

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

    // info settings ---------------------------------------------------------------------------------------------------

    /** @var bool|null - show comments with additional info (readable values, object hashes, hints...) */
    public static $showInfo = true;

    /** @var int - show length of strings from n characters */
    public static $lengthInfoMin = 5;

    /** @var DateTimeZone|string|null - timezone used to format timestamps to readable date/time (php.ini timezone by default) */
    public static $infoTimeZone;

    /** @var bool - calculate total memory size of big structures (even below $maxDepth) */
    //public static $infoSize = false;

    // backtrace settings ----------------------------------------------------------------------------------------------

    /** @var int - number of trace lines below a dump */
    public static $traceLength = 1;

    /** @var bool - show class, method, arguments in backtrace */
    public static $traceDetails = true;

    /** @var int - depth of dumped arguments of called function in backtrace */
    public static $traceArgsDepth = 0;

    /** @var int[] - count of lines of code shown for each filtered frame. [5] means 5 lines for first, 0 for others... */
    public static $traceCodeLines = [5];

    /** @var string[] - functions, classes and methods skipped from backtrace */
    public static $traceSkip = [
        '~^Dogma\\\\Debug\\\\~', // Debugger classes
        '~^(ld|rd|rc|rb|rf|rl|rt)$~', // shortcut functions
        '~^call_user_func~', // proxies
        '~^Composer\\\\Autoload\\\\~', // composer loaders
        '~^loadClass~',
    ];

    /** @var string - common path prefix to remove from all paths */
    public static $trimPathPrefix = '';

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

    /** @var bool - turn on/of user formatters for dumps */
    public static $useFormatters = true;

    /** @var array<class-string|string, callable> - formatters for user-formatted dumps */
    public static $formatters = [
        Callstack::class => [self::class, 'dumpCallstack'],

        DateTimeInterface::class => [self::class, 'dumpDateTimeInterface'],
        Date::class => [self::class, 'dumpDate'],
        Time::class => [self::class, 'dumpTime'],

        DateTimeInterval::class => [self::class, 'dumpDateTimeInterval'],
        TimeInterval::class => [self::class, 'dumpTimeInterval'],
        DateInterval::class => [self::class, 'dumpDateOrNightInterval'],
        NightInterval::class => [self::class, 'dumpDateOrNightInterval'],
        DateIntervalData::class => [self::class, 'dumpDateOrNightIntervalData'],
        NightIntervalData::class => [self::class, 'dumpDateOrNightIntervalData'],

        IntervalSet::class => [self::class, 'dumpIntervalSet'],
        ModuloIntervalSet::class => [self::class, 'dumpIntervalSet'],
        DateIntervalDataSet::class => [self::class, 'dumpIntervalSet'],
        NightIntervalDataSet::class => [self::class, 'dumpIntervalSet'],

        IntEnum::class => [self::class, 'dumpIntEnum'],
        StringEnum::class => [self::class, 'dumpStringEnum'],
        IntSet::class => [self::class, 'dumpIntSet'],
        StringSet::class => [self::class, 'dumpStringSet'],
        MultiEnum::class => [self::class, 'dumpConsistenceMultiEnum'], // must precede Enum
        Enum::class => [self::class, 'dumpConsistenceEnum'],

        DOMDocument::class => [self::class, 'dumpDomDocument'],
        DOMDocumentFragment::class => [self::class, 'dumpDomDocumentFragment'],
        DOMDocumentType::class => [self::class, 'dumpDomDocumentType'],
        DOMEntity::class => [self::class, 'dumpDomEntity'],
        DOMElement::class => [self::class, 'dumpDomElement'],
        DOMNodeList::class => [self::class, 'dumpDomNodeList'],
        DOMCdataSection::class => [self::class, 'dumpDomCdataSection'],
        DOMComment::class => [self::class, 'dumpDomComment'],
        DOMText::class => [self::class, 'dumpDomText'],
        DOMAttr::class => [self::class, 'dumpDomAttr'],
        Element::class => [self::class, 'dumpDomElement'],
        NodeList::class => [self::class, 'dumpDomNodeList'],

        'stream resource' => [self::class, 'dumpStream'],
    ];

    /** @var array<class-string|string, callable> - formatters for short dumps (single line) */
    public static $shortFormatters = [
        '' => [self::class, 'dumpEntityId'],
    ];

    /** @var array<class-string> - classes that are not traversed. short dumps are used if configured */
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
            DebugClient::send(Packet::ERROR, $message);

            return '';
        }

        $dump = str_replace(']=>', '] =>', $dump);
        $dump = preg_replace('~=>\n\s+~', '=> ', $dump);
        $dump = preg_replace('~{\n\s+}~', '{ }', $dump);

        if ($colors) {
            $dump = Str::replaceKeys($dump, [
                '*RECURSION*' => self::exceptions('RECURSION'),
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

        return $dump;
    }

    /**
     * @param mixed $value
     */
    public static function dump($value, ?int $maxDepth = null, ?int $traceLength = null): string
    {
        $maxDepthBefore = self::$maxDepth;
        if ($maxDepth !== null) {
            self::$maxDepth = $maxDepth;
        }
        $traceLengthBefore = self::$traceLength;
        if ($traceLength !== null) {
            self::$traceLength = $traceLength;
        }

        try {
            $callstack = Callstack::get()->filter(self::$traceSkip);

            $expression = self::$dumpExpressions ? self::findExpression($callstack) : null;

            $result = self::dumpValue($value, 0, $expression);

            $trace = self::formatCallstack($callstack, $traceLength, 0, []);

            // mark ordinary arrays as literals
            if ($expression !== null && $expression[0] === '[' && !is_callable($value)) {
                $expression = null;
            }
            $expression = self::$dumpExpressions ? self::key($expression ?? 'literal') . self::symbol(':') . ' ' : '';

            return $expression . $result . ($trace ? "\n" : '') . $trace;
        } finally {
            self::$traceLength = $traceLengthBefore;
            self::$maxDepth = $maxDepthBefore;
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
            if (is_string($key) && substr($key, -7) === '::class') {
                return self::dumpClass($value, $depth);
            } else {
                return self::dumpString($value, $depth, (string) $key);
            }
        } elseif (is_array($value)) {
            // dump as callable when called as `dump([$foo, 'bar'])`
            if (count($value) === 2 && is_callable($value) && $depth === 0 && is_string($key) && $key[0] === '[') {
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
        } elseif (is_resource($value) || gettype($value) === 'resource (closed)') {
            return self::dumpResource($value, $depth);
        } else {
            throw new LogicException('Unknown type: ' . gettype($value));
        }
    }

    public static function dumpInt(int $int, string $key = ''): string
    {
        $sign = $int < 0 ? '-' : '';
        $int = abs($int);
        $key = str_replace('_', '', strtolower($key));

        $info = '';
        if (self::$showInfo) {
            if ($int === PHP_INT_MAX) {
                $info = ' ' . self::info('// PHP_INT_MAX');
            } elseif ($int === PHP_INT_MIN) {
                $info = ' ' . self::info('// PHP_INT_MIN');
            } elseif (!$sign && $int > 10000000 && Str::contains($key, 'time')) {
                $time = self::intToFormattedDate($int);
                $info = ' ' . self::info('// ' . $time);
            } elseif (!$sign && $int > 1024 && preg_match('/size|bytes/', $key)) {
                $info = ' ' . self::info('// ' . self::size($int));
            } elseif (!$sign && preg_match('/flags|options|headeropt|settings/', $key)) {
                $info = ' ' . self::info('// ' . implode('|', array_reverse(self::binaryComponents($int))));
            } else {
                $exp = null;
                for ($n = 9; $n < 63; $n++) {
                    if ($int === 2 ** $n) {
                        $exp = $n;
                    } elseif ($int + 1 === 2 ** $n) {
                        $exp = $n . '-1';
                    }
                }
                if ($exp !== null) {
                    $info = ' ' . self::info("// 2^$exp");
                }
            }
        }

        if (!$sign && $int <= 0777 && preg_match('/filemode|permissions/', $key)) {
            return self::int('0' . decoct($int)) . $info;
        } else {
            return self::int($sign . $int) . $info;
        }
    }

    public static function dumpFloat(float $float, string $key = ''): string
    {
        $decimal = (float) (int) $float === $float ? '.0' : '';

        $info = '';
        if (self::$showInfo && $float > 1000000 && stripos($key, 'time') !== false) {
            /** @var DateTime $time */
            $time = DateTime::createFromFormat('U.uP', $float . $decimal . 'Z');
            $time = $time->setTimezone(self::getTimeZone())->format('Y-m-d H:i:s.uP');
            $info = ' ' . self::info('// ' . $time);
        }

        return self::float($float . $decimal) . $info;
    }

    public static function dumpString(string $string, int $depth = 0, string $key = ''): string
    {
        $callable = is_callable($string)
            ? ', callable'
            : '';

        $bytes = strlen($string);
        $length = Str::length($string, self::$stringsEncoding);

        $hidden = '';
        if (in_array($key, self::$hiddenFields, true)) {
            $string = '*****';
            $hidden = ', hidden';
        }

        $trimmed = '';
        if ($length > self::$maxLength) {
            $string = Str::trim($string, self::$maxLength, self::$stringsEncoding) . '…';
            $trimmed = ', trimmed';
        }

        // phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedElseif
        // phpcs:disable SlevomatCodingStandard.ControlStructures.AssignmentInCondition.AssignmentInCondition
        static $uuidRe = '~(?:urn:uuid:)?{?([0-9a-f]{8})-?([0-9a-z]{4})-?([0-9a-z]{4})-?([0-9a-z]{4})-?([0-9a-z]{12})}?~';
        if (!self::$showInfo) {
            $info = '';
        } elseif (preg_match($uuidRe, $string, $m) && $info = self::uuidInfo($m)) {
            // ^^^
        } elseif ($bytes === 32 && Str::contains($key, 'id') && $info = self::binaryUuidInfo($string)) {
            // ^^^
        } elseif ($key !== null && Ansi::isColor($string, !preg_match('~color|background~i', $key))) {
            $info = ' ' . self::info("// " . Ansi::rgb('     ', null, $string));
        } elseif ($bytes === $length && $bytes <= self::$lengthInfoMin && !$trimmed && !$hidden && !$callable) {
            $info = '';
        } elseif ($bytes === $length) {
            $info = ' ' . self::info("// $bytes B{$trimmed}{$hidden}{$callable}");
        } else {
            $info = ' ' . self::info("// $bytes B, $length ch{$trimmed}{$hidden}{$callable}");
        }

        // explode path on more lines
        if ($key !== null && preg_match('/path(?!ext)/i', $key) && Str::contains($string, PATH_SEPARATOR)) {
            $infoBefore = self::$showInfo;
            self::$showInfo = false;
            $parts = explode(PATH_SEPARATOR, $string);
            foreach ($parts as $i => $s) {
                if ($s !== "") {
                    $parts[$i] = self::dumpString($s . ($i + 1 === count($parts) ? '' : PATH_SEPARATOR));
                } else {
                    unset($parts[$i]);
                }
            }
            $sep = "\n" . self::indent($depth) . ' ' . self::symbol('.') . ' ';
            self::$showInfo = $infoBefore;

            return implode($sep, $parts) . $info;
        }

        return self::string($string) . $info;
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

            return self::bracket('[') . ' ' . self::exceptions('RECURSION') . ' ' . self::bracket(']') . $info;
        }

        if ($depth >= self::$maxDepth) {
            $info = self::$showInfo ? ' ' . self::info("// $count item" . ($count > 1 ? 's' : '')) : '';

            return self::bracket('[') . ' ' . self::exceptions('...') . ' ' . self::bracket(']') . $info;
        }

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
                $item = $isList && !self::$alwaysShowArrayKeys
                    ? self::dumpValue($value, $depth + 1)
                    : self::key($k) . ' ' . self::symbol('=>') . ' ' . self::dumpValue($value, $depth + 1, $k);

                $pos = strrpos($item, $infoPrefix);
                if ($pos !== false && !strpos(substr($item, $pos), "\n")) {
                    $item = substr($item, 0, $pos) . $coma . substr($item, $pos);
                    $hasInfo = true;
                } else {
                    $item .= $coma;
                }

                $items[] = $item;
            }
        } finally {
            unset($array[$marker]);
        }

        $info = self::$showInfo ? ' ' . self::info("// $count item" . ($count > 1 ? 's' : '')) : '';
        $start = self::bracket('[');
        $end = self::bracket(']') . $info;

        $length = Ansi::length(implode(', ', $items), self::$stringsEncoding);

        if ($isList && $length < self::$shortArrayMaxLength && !$hasInfo) {
            // simple values: "[1, 2, 3] // 3 items"
            return $start . substr(implode(' ', $items), 0, -strlen($coma)) . $end;
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

            return $start . substr(implode(' ', $values), 0, -strlen($coma))
                . $end . self::info(' (' . implode(', ', $infos) . ')');
        } else {
            // item per line
            $indent = self::indent($depth);
            $indent2 = self::indent($depth + 1);

            return $start . "\n" . $indent2 . implode("\n" . $indent2, $items) . "\n" . $indent . $end;
        }
    }

    /**
     * @param object $object
     */
    public static function dumpObject($object, int $depth = 0): string
    {
        $short = spl_object_hash($object);
        $recursion = isset(self::$objects[$short]);
        $class = get_class($object);

        if (!$recursion && $object instanceof Pokeable) {
            $object->poke();
        }

        $info = '';
        if (self::$showInfo) {
            $info = ' ' . self::info('// #' . self::objectHash($object));
        }

        if ($recursion) {
            return self::name($class) . ' ' . self::bracket('{') . ' '
                . self::exceptions('RECURSION') . ' ' . self::bracket('}') . $info;
        }

        $handlerResult = '';
        if (self::$useFormatters && $depth < self::$maxDepth + 1) {
            $class = get_class($object);
            $handler = self::$formatters[$class] ?? null;
            if ($handler !== null) {
                $handlerResult = $handler($object);
            }

            foreach (self::$formatters as $cl => $handler) {
                if (is_a($object, $cl)) {
                    $handlerResult = $handler($object, $depth);
                    break;
                }
            }
        }

        if ($depth >= self::$maxDepth || in_array($class, self::$doNotTraverse, true)) {
            if ($handlerResult !== '' && !Str::contains($handlerResult, "\n")) {
                return $handlerResult;
            }

            $short = '';
            foreach (self::$shortFormatters as $cl => $handler) {
                if ($cl !== '' && is_a($object, $cl)) {
                    $short = $handler($object);
                }
            }
            if ($short === '' && isset(self::$shortFormatters[''])) {
                $short = (self::$shortFormatters[''])($object);
            }

            return self::name($class) . ' ' . self::bracket('{') . ' ' . $short . ($short ? ' ' : '')
                . self::exceptions('...') . ' ' . self::bracket('}') . $info;
        }

        if ($handlerResult !== '') {
            return $handlerResult;
        }

        $indent = self::indent($depth + 1);
        $equal = ' ' . self::symbol('=') . ' ';
        $semi = self::symbol(';');
        $infoPrefix = self::infoPrefix();

        $n = 0;
        $items = [];
        //$properties = (new ReflectionObject($object))->getProperties();
        $properties = (array) $object;
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

        return self::name($class) . ' ' . self::bracket('{') . $info
            . "\n" . implode("\n", $items) . "\n" . self::indent($depth) . self::bracket('}');
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
            if ($pos !== false && Str::contains(substr($item, $pos), "\n")) {
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

        $start = self::name($class) . self::symbol('::') . self::name('class') . ' ' . self::bracket('{');

        return $start . "\n" . implode("\n", $items) . "\n" . self::bracket('}');
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

        if ($lines !== false) {
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

            $name = Str::contains($ref->getName(), '{closure}')
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
     * @param resource|int $resource
     */
    public static function dumpResource($resource, int $depth = 0): string
    {
        $type = is_resource($resource) ? get_resource_type($resource) : 'closed';
        $name = $type . ' resource';

        foreach (self::$formatters as $class => $handler) {
            if ($class === $name) {
                return $handler($resource, $depth);
            }
        }

        $info = self::$showInfo ? ' ' . self::info('#' . (int) $resource) : '';

        return self::resource($name) . $info;
    }

    public static function highlightUrl(string $url): string
    {
        $url = (string) preg_replace('/([a-zA-Z0-9_-]+)=/', Ansi::dyellow('$1') . '=', $url);
        $url = (string) preg_replace('/=([a-zA-Z0-9_-]+)/', '=' . Ansi::lcyan('$1'), $url);
        $url = (string) preg_replace('/[\\/?&=]/', Ansi::dgray('$0'), $url);

        return $url;
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    /**
     * @param object $object
     * @return string
     */
    public static function objectHash($object): string
    {
        return substr(md5(spl_object_hash($object)), 0, 4);
    }

    public static function binaryUuidInfo(string $uuid): ?string
    {
        $uuid = bin2hex($uuid);
        $formatted = substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-'
            . substr($uuid, 12, 4) . '-' . substr($uuid, 16, 4) . '-' . substr($uuid, 20, 12);

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

            return 'UUID v' . $version . ', ' . self::intToFormattedDate($time);
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

}
