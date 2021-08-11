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
use DateTime;
use DateTimeZone;
use Dogma\Pokeable;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionObject;
use function array_keys;
use function array_map;
use function array_shift;
use function array_slice;
use function array_unshift;
use function debug_backtrace;
use function explode;
use function get_class;
use function get_resource_type;
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
use function min;
use function preg_match;
use function spl_object_hash;
use function str_replace;
use function stripos;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use function uniqid;
use const PHP_INT_MAX;

class Dumper
{
    use DumperFormatters;
    use DumperHandlers;
    use DumperTraces;

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

    /** @var int */
    public static $maxDepth = 3;

    /** @var int */
    public static $maxLength = 10000;

    /** @var int */
    public static $shortArrayMaxLength = 100;

    /** @var int */
    public static $shortArrayMaxItems = 6;

    /** @var string */
    public static $stringsEncoding = 'utf-8';

    /** @var string[] */
    public static $hiddenFields = [];

    /** @var int unicode strings escaping/formatting */
    public static $stringsOutput = self::UNICODE_ESCAPE_ONLY;

    /** @var int binary strings escaping/formatting */
    public static $binaryOutput = self::BINARY_ESCAPE_ONLY;

    /** @var bool show comments with additional info (readable values, object hashes, hints...) */
    public static $showInfo = true;

    /** @var DateTimeZone|string|null php.ini timezone by default */
    public static $infoTimeZone;

    /** @var int */
    public static $propertyOrder = self::ORDER_VISIBILITY_ALPHABETIC;

    /** @var string[] ($long => $short) */
    public static $namespaceReplacements = [];

    /** @var string[] */
    private static $objects = [];

    /**
     * @param mixed $value
     */
    public static function dump($value, ?int $maxDepth = null, ?int $traceLength = null, ?int $order = null): string
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
            $traceLines = debug_backtrace();

            $name = self::extractName($traceLines);
            $value = self::dumpValue($value, 0, $name);
            $trace = self::formatTrace($traceLines, $order);
            if ($name !== null && $name[0] === '[') {
                $name = null;
            }

            return ($name ?? 'literal') . self::symbol(':') . ' ' . $value . "\n" . $trace;
        } finally {
            self::$traceLength = $traceLengthBefore;
            self::$maxDepth = $maxDepthBefore;
        }
    }

    /**
     * @param mixed $value
     * @param int $depth
     * @param string|int|null $key
     * @return string
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
                return self::dumpString($value, (string) $key);
            }
        } elseif (is_array($value)) {
            return self::dumpArray($value, $depth, $key);
        } elseif (is_object($value)) {
            return self::dumpObject($value, $depth);
        } elseif (is_resource($value)) {
            return self::dumpResource($value, $depth);
        } else {
            throw new LogicException('Unknown value.');
        }
    }

    public static function dumpInt(int $int, $key = null): string
    {
        $sign = $int < 0 ? '-' : '';
        $int = abs($int);
        $key = strtolower((string) $key);

        $info = '';
        if (self::$showInfo) {
            if ($int === PHP_INT_MAX) {
                $info = ' ' . self::info('// PHP_INT_MAX');
            } elseif ($int === PHP_INT_MAX) {
                $info = ' ' . self::info('// PHP_INT_MIN');
            } elseif (!$sign && $int > 1000000 && strpos($key, 'time') !== false) {
                $time = DateTime::createFromFormat('UP', $int . 'Z')->setTimezone(self::getTimeZone())->format('Y-m-d H:i:sP');
                $info = ' ' . self::info('// ' . $time);
            } elseif (!$sign && preg_match('/flags|options|settings/', $key)) {
                $info = ' ' . self::info('// ' . implode('|', array_reverse(self::binaryComponents($int))));
            } else {
                $exp = null;
                for ($n = 7; $n < 63; $n++) {
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

        return self::int($sign . $int) . $info;
    }

    public static function dumpFloat(float $float, $key = null): string
    {
        $decimal = (float) (int) $float === $float ? '.0' : '';

        $info = '';
        if (self::$showInfo && $float > 1000000 && stripos((string) $key, 'time') !== false) {
            $time = DateTime::createFromFormat('U.uP', $float . $decimal . 'Z')->setTimezone(self::getTimeZone())->format('Y-m-d H:i:s.uP');
            $info = ' ' . self::info('// ' . $time);
        }

        return self::float($float . $decimal) . $info;
    }

    public static function dumpString(string $string, ?string $key = null): string
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

        $info = '';
        if (self::$showInfo) {
            $info = $bytes === $length
                ? ' ' . self::info("// $bytes B{$trimmed}{$hidden}{$callable}")
                : ' ' . self::info("// $bytes B, $length ch{$trimmed}{$hidden}{$callable}");
        }

        return self::string($string) . $info;
    }

    /**
     * @param int|string|null $key
     */
    public static function dumpArray(array $array, int $depth = 0, $key = null): string
    {
        static $marker;
        if ($marker === null) {
            $marker = uniqid("\x00", true);
        }

        if ($array === []) {
            return self::bracket('[]');
        }

        // dump as callable when called as `dump([$foo, 'bar'])`
        if (count($array) === 2 && is_callable($array) && $depth === 0 && is_string($key) && $key[0] === '[') {
            return self::dumpMethod($array, $depth);
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
        $infoPrefix = self::infoPrefix();

        $hasInfo = false;
        $items = [];
        try {
            $array[$marker] = true;
            foreach ($array as $k => $value) {
                if ($k === $marker) {
                    continue;
                }
                $item = $isList
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

        $length = Colors::length(implode(', ', $items), self::$stringsEncoding);

        if ($isList && $length  < self::$shortArrayMaxLength && !$hasInfo) {
            // simple values: "[1, 2, 3] // 3 items"
            return $start . substr(implode(' ', $items), 0, -strlen($coma)) . $end;
        } elseif ($isList && $length  < self::$shortArrayMaxLength && $count < self::$shortArrayMaxItems) {
            // squish lines: "['a', 'bc'] // 2 items (1 B, 2 B)"
            $values = [];
            $infos = [];
            foreach ($items as $item) {
                $parts = explode($infoPrefix, $item);
                $parts[] = '';
                [$v, $i] = $parts;
                $i = str_replace("\x1B[0m", '', $i);
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
                . self::exceptions('RECURSION') . ' '. self::bracket('}') . $info;
        }

        $handlerResult = '';
        if (self::$useHandlers && $depth < self::$maxDepth + 1) {
            $class = get_class($object);
            $handler = self::$handlers[$class] ?? null;
            if ($handler !== null) {
                $handlerResult = $handler($object);
            }

            foreach (self::$handlers as $cl => $handler) {
                if (is_a($object, $cl)) {
                    $handlerResult = $handler($object, $depth);
                    break;
                }
            }
        }

        if ($depth >= self::$maxDepth || in_array($class, self::$doNotTraverse)) {
            if ($handlerResult !== '' && strpos($handlerResult, "\n") === false) {
                return $handlerResult;
            }

            $short = '';
            foreach (self::$shortHandlers as $cl => $handler) {
                if (is_a($object, $cl)) {
                    $short = ' ' . $handler($object);
                }
            }
            if ($short === '' && isset(self::$shortHandlers[null])) {
                $short = ' ' . (self::$shortHandlers[null])($object);
            }

            return self::name($class) . ' ' . self::bracket('{') . $short . ' '
                . self::exceptions('...') . ' ' . self::bracket('}') . $info;
        }

        if ($object instanceof Closure) {
            return self::dumpClosure($object, $depth);
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
            $parts = explode("\x00", $name);
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
            $value = self::dumpValue($property->getValue($class), $depth + 1, $property->getName());

            $item = $indent . $access . ' ' . $name . $equal . $value;

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

        $start = self::name($class) . self::symbol('::') . self::name('class') . ' ' . self::bracket('{');

        return $start . "\n" . implode("\n", $items) . "\n" . self::bracket('}');
    }

    public static function dumpClosure(Closure $closure, int $depth = 0): string
    {
        $ref = new ReflectionFunction($closure);

        $variables = $ref->getStaticVariables();

        $lines = @file($ref->getFileName());
        if ($lines !== false) {
            $lines = array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1);

            $startLine = array_shift($lines);
            $start = min(
                strpos($startLine, 'function') ?: PHP_INT_MAX,
                strpos($startLine, 'fn') ?: PHP_INT_MAX,
                strpos($startLine, 'static') ?: PHP_INT_MAX
            );
            $startLine = substr($startLine, $start);
            array_unshift($lines, $startLine);
            $lines = array_map(static function (string $line): string {
                return trim($line);
            }, $lines);
            $lines = implode(' ', $lines);

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
            $head = "function ($params)";
            if ($variables !== []) {
                $vars = implode(', ', array_map(static function (string $var): string {
                    return '$' . $var;
                }, array_keys($variables)));
                $head .= " use ($vars)";
            }
        }

        $head = self::name('Closure') . ' ' . self::closure($head) . self::bracket('{');
        $variables = self::dumpVariables($variables, $depth);
        $file = self::$showInfo ? self::info(' // ') . self::fileLine($ref->getFileName(), $ref->getStartLine()) : '';

        return $variables
            ? $head . $file . $variables . self::indent($depth) . self::bracket('}')
            : $head . self::bracket('}') . $file;
    }

    public static function dumpMethod(array $callable, int $depth = 0): string
    {
        if (!is_callable($callable)) {
            throw new InvalidArgumentException('Callable array expected.');
        }

        [$object, $method] = $callable;

        if (is_object($object)) {
            $ref = (new ReflectionObject($object))->getMethod($method);
            $name = '';
        } else {
            $ref = (new ReflectionClass($object))->getMethod($method);
            $name = self::name($object) . self::symbol('::')
                . self::name($method) . self::bracket('()');
        }

        $variables = self::dumpVariables($ref->getStaticVariables(), $depth, true);

        return $name . ' ' . self::bracket('{') . $variables . self::bracket('}');
    }

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
     * @param resource $resource
     * @param int $depth
     * @return string
     */
    public static function dumpResource($resource, int $depth = 0): string
    {
        $type = is_resource($resource) ? get_resource_type($resource) : 'closed';
        $name = $type . ' resource';

        foreach (self::$handlers as $class => $handler) {
            if ($class === $name) {
                return $handler($resource, $depth);
            }
        }

        $info = self::$showInfo ? ' ' . self::info('#' . (int) $resource) : '';

        return self::resource($name) . $info;
    }

    public static function dumpBacktrace(array $traces, bool $withArgs = true): string
    {
        $items = [];
        foreach ($traces as $trace) {
            $args = '';
            if ($withArgs && !empty($trace['args'])) {
                $args = self::dumpArray($trace['args']);
                $args = substr($args, strlen(self::bracket('[')), strrpos($args, self::bracket(']')));
            }

            $fileLine = $trace['file'] ? self::fileLine($trace['file'], $trace['line']) : '?';

            $items[] = self::info('^---') . ' ' . self::info('in') . ' ' . $fileLine
                . ' ' . self::info('-') . ' '
                . self::nameDim($trace['class'] ?? '') . self::symbol($trace['type'] ?? '')
                . self::nameDim($trace['function'])
                . self::bracket('(') . $args . self::bracket(')');
        }

        return implode("\n", $items);
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    public static function objectHash($object): string
    {
        return substr(md5(spl_object_hash($object)), 0, 4);
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
     * @param int $number
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
