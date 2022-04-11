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
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use mysqli;
use ReflectionFunction;
use ReflectionObject;
use UnitEnum;
use function abs;
use function array_diff;
use function array_keys;
use function array_map;
use function array_pop;
use function array_reverse;
use function array_values;
use function basename;
use function bin2hex;
use function count;
use function dechex;
use function decoct;
use function dirname;
use function end;
use function explode;
use function function_exists;
use function get_class;
use function get_extension_funcs;
use function get_loaded_extensions;
use function hexdec;
use function implode;
use function in_array;
use function ini_get;
use function is_array;
use function is_callable;
use function is_int;
use function is_object;
use function is_resource;
use function is_scalar;
use function is_string;
use function json_encode;
use function key;
use function ltrim;
use function md5;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function property_exists;
use function range;
use function spl_object_hash;
use function spl_object_id;
use function str_pad;
use function str_repeat;
use function str_replace;
use function stream_context_get_params;
use function stream_get_meta_data;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use const PATH_SEPARATOR;
use const STR_PAD_LEFT;

trait DumperFormatters
{

    /** @var string[] */
    private static $phpEscapes = [
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

    /** @var string[] */
    private static $jsEscapes = [
        "\x00" => '\0',
        "\x08" => '\b', // 08
        "\f" => '\f', // 0c
        "\n" => '\n', // 0a
        "\r" => '\r', // 0d
        "\t" => '\t', // 09
        "\v" => '\v', // 0b
        '\\' => '\\\\',
        '"' => '\"',
    ];

    /** @var string[] */
    private static $jsonEscapes = [
        "\x08" => '\b', // 08
        "\f" => '\f', // 0c
        "\n" => '\n', // 0a
        "\r" => '\r', // 0d
        "\t" => '\t', // 09
        '\\' => '\\\\',
        '"' => '\"',
    ];

    /** @var string[] */
    private static $mysqlEscapes = [
        "\x00" => '\0', // 00
        "\x08" => '\b', // 08
        "\n" => '\n', // 0a
        "\r" => '\r', // 0d
        "\t" => '\t', // 09
        "\x1a" => '\Z', // 1a (legacy Win EOF)
        '\\' => '\\\\',
        '%' => '\%',
        '_' => '\_',
        "'" => "\'",
        '"' => '\"',
    ];

    // objects and resources -------------------------------------------------------------------------------------------

    /**
     * @param object $object
     */
    public static function dumpEntityId($object): string
    {
        $id = '';
        if (property_exists($object, 'id')) {
            $ref = new ReflectionObject($object);
            $prop = $ref->getProperty('id');
            $prop->setAccessible(true);
            $value = $prop->getValue($object);
            $id = self::dumpValue($value);
        }

        return $id;
    }

    /**
     * @param resource $resource
     */
    public static function dumpStream($resource, int $depth = 0): string
    {
        $id = (int) $resource;

        return self::resource("(stream $id)") . ' ' . self::bracket('{')
            . self::dumpVariables(stream_get_meta_data($resource), $depth)
            . self::indent($depth) . self::bracket('}');
    }

    /**
     * @param resource $resource
     */
    public static function dumpStreamContext($resource, int $depth = 0): string
    {
        $id = (int) $resource;
        $params = stream_context_get_params($resource);
        if ($params !== ['options' => []]) {
            $params = self::dumpVariables($params, $depth) . self::indent($depth);

            return self::resource("(stream-context $id)") . ' ' . self::bracket('{')
                . $params . self::bracket('}');
        } else {
            return self::resource('(stream-context)') . ' ' . self::info('#' . (int) $resource);
        }
    }

    public static function dumpUnitEnum(UnitEnum $enum): string
    {
        return self::name(get_class($enum)) . self::symbol('::') . self::name($enum->name);
    }

    public static function dumpBackedEnum(BackedEnum $enum): string
    {
        $value = is_int($enum->value) ? self::int((string) $enum->value) : self::string($enum->value);

        return self::name(get_class($enum)) . self::symbol('::') . self::name($enum->name)
            . self::bracket('(') . $value . self::bracket(')');
    }

    public static function dumpCallstack(Callstack $callstack, int $depth = 0): string
    {
        return self::name(get_class($callstack)) . ' ' . self::dumpValue($callstack->frames, $depth);
    }

    public static function dumpDateTimeInterface(DateTimeInterface $dt): string
    {
        $value = str_replace('.000000', '', $dt->format('Y-m-d H:i:s.u'));
        $timeZone = $dt->format('P') === $dt->getTimezone()->getName() ? '' : ' ' . self::value($dt->getTimezone()->getName());
        $dst = $dt->format('I') ? ' ' . self::value2('DST') : '';
        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($dt)) : '';

        return self::name(get_class($dt)) . self::bracket('(')
            . self::value($value) . self::value2($dt->format('P')) . $timeZone . $dst
            . self::bracket(')') . $info;
    }

    public static function dumpMysqli(mysqli $mysqli, int $depth = 0): string
    {
        $properties = [];
        // filter unnecessary info (cannot cast native class to array :E)
        $ref = new ReflectionObject($mysqli);
        foreach ($ref->getProperties() as $property) {
            $name = $property->getName();
            $value = @$property->getValue($mysqli); // "Property access is not allowed yet" bullshit
            if ($value === null) {
                continue;
            } elseif ($name === 'client_version' || $name === 'server_version') {
                continue;
            } elseif ($value === 0 && in_array($name, ['connect_errno', 'errno', 'warning_count', 'field_count', 'insert_id'], true)) {
                continue;
            } elseif ($value === '' && $name === 'error') {
                continue;
            } elseif ($value === [] && $name === 'error_list') {
                continue;
            } elseif ($value === '00000' && $name === 'sqlstate') {
                continue;
            }
            $properties[$name] = $value;
        }
        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($mysqli)) : '';

        return self::name(get_class($mysqli)) . ' ' . self::bracket('{')
            . self::dumpVariables($properties, $depth + 1) . self::bracket('}') . $info;
    }

    // scalars ---------------------------------------------------------------------------------------------------------

    public static function dumpTimestamp(int $int): ?string
    {
        if ($int < 10000000) {
            return null;
        }

        return self::int((string) $int) . ' ' . self::info('// ' . self::intToFormattedDate($int));
    }

    public static function dumpFloatTimestamp(float $float): ?string
    {
        if ($float < 1000000) {
            return null;
        }

        $decimal = (float) (int) $float === $float ? '.0' : '';

        /** @var DateTime $time */
        $time = DateTime::createFromFormat('U.uP', $float . $decimal . 'Z');
        $time = $time->setTimezone(self::getTimeZone())->format('Y-m-d H:i:s.uP');

        return self::float($float . $decimal) . ' ' . self::info('// ' . $time);
    }

    public static function dumpPermissions(int $int): ?string
    {
        if ($int < 0) {
            return null;
        }

        $perms = (($int & 0400) ? 'r' : '-')
            . (($int & 0200) ? 'w' : '-')
            . (($int & 0100) ? 'x' : '-')
            . (($int & 0040) ? 'r' : '-')
            . (($int & 0020) ? 'w' : '-')
            . (($int & 0010) ? 'x' : '-')
            . (($int & 0004) ? 'r' : '-')
            . (($int & 0002) ? 'w' : '-')
            . (($int & 0001) ? 'x' : '-');

        return self::int(str_pad(decoct($int), 4, '0', STR_PAD_LEFT)) . ' ' . self::info('// ' . $perms);
    }

    public static function dumpSize(int $int): ?string
    {
        if ($int < 1024) {
            return null;
        }

        return self::int((string) $int) . ' ' . self::info('// ' . Units::size($int));
    }

    public static function dumpFlags(int $int): ?string
    {
        if ($int < 0) {
            return null;
        }

        $info = implode('|', array_reverse(self::binaryComponents($int)));

        return self::int((string) $int) . ' ' . self::info('// ' . $info);
    }

    public static function dumpPowersOfTwo(int $int): ?string
    {
        $abs = abs($int);
        $exp = null;
        for ($n = 9; $n < 63; $n++) {
            if ($abs === 2 ** $n) {
                $exp = $n;
            } elseif ($abs + 1 === 2 ** $n) {
                $exp = $n . '-1';
            }
        }
        if ($exp === null) {
            return null;
        }

        return self::int((string) $int) . ' ' . self::info("// 2^$exp");
    }

    public static function dumpHiddenString(string $string, string $info, string $key, int $depth): ?string
    {
        $key2 = ltrim($key, '$');
        if (!in_array($key, self::$hiddenFields, true) && !in_array($key2, self::$hiddenFields, true)) {
            return null;
        }

        $quote = Ansi::color('"', self::$colors['string']);

        if (Str::endsWith($info, ', trimmed')) {
            $info = substr($info, 0, -9);
        }
        $info .= $info ? ', hidden' : 'hidden';
        $info = ' ' . self::info("// $info");

        return $quote . self::exceptions('*****') . $quote . $info;
    }

    public static function dumpPathList(string $string, string $info, string $key, int $depth): ?string
    {
        if (!Str::contains($string, PATH_SEPARATOR)) {
            return null;
        }

        return self::string($string, $depth, PATH_SEPARATOR) . ' ' . self::info("// $info");
    }

    public static function dumpPath(string $string, string $info, string $key, int $depth): ?string
    {
        if (preg_match('~file|path~', $key)
            || preg_match('~^[a-z]:[/\\\\]~i', $string)
            || ($string !== '' && $string[0] === '/')
            || Str::contains($string, '/../')
        ) {
            $path = self::normalizePath($string);
            $path = ($path !== $string) ? ', ' . $path : '';

            return self::string($string, $depth) . ' ' . self::info("// $info{$path}");
        }

        return null;
    }

    public static function dumpUuid(string $string, string $info, string $key, int $depth): ?string
    {
        static $uuidRe = '~(?:urn:uuid:)?{?([0-9a-f]{8})-?([0-9a-z]{4})-?([0-9a-z]{4})-?([0-9a-z]{4})-?([0-9a-z]{12})}?~';

        $bytes = strlen($string);
        // phpcs:disable SlevomatCodingStandard.ControlStructures.AssignmentInCondition.AssignmentInCondition
        if ((preg_match($uuidRe, $string, $m) && $uuidInfo = self::uuidInfo($m))
            || ($bytes === 32 && preg_match('~id$~i', $key) && $uuidInfo = self::binaryUuidInfo($string))
        ) {
            return self::string($string, $depth) . ' ' . self::info('// ' . $uuidInfo);
        }

        return null;
    }

    public static function dumpColor(string $string, string $info, string $key, int $depth): ?string
    {
        if (!Ansi::isColor($string, !preg_match('~color|background~i', $key))) {
            return null;
        }

        return self::string($string, $depth) . ' ' . self::info("// " . Ansi::rgb('     ', null, $string));
    }

    public static function dumpCallableString(string $string, string $info, string $key, int $depth): ?string
    {
        if (!is_callable($string)) {
            return null;
        }

        $info .= $info ? ', ' : '';
        $ref = new ReflectionFunction($string);
        if ($ref->isUserDefined()) {
            $file = $ref->getFileName();
            $line = $ref->getStartLine();

            // todo: trim file prefix
            $info .= "callable defined in $file:$line";

            return self::string($string, $depth) . ' ' . self::info("// $info");
        } else {
            foreach (get_loaded_extensions() as $extension) {
                if (in_array($string, get_extension_funcs($extension) ?: [], true)) {
                    $extension = strtolower($extension);
                    $info .= "callable from ext-$extension";

                    return self::string($string, $depth) . ' ' . self::info("// $info");
                }
            }

            $info .= "callable";

            return self::string($string, $depth) . ' ' . self::info("// $info");
        }
    }

    // component formatters --------------------------------------------------------------------------------------------

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

    public static function exceptions(string $string): string
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

    public static function access(string $string): string
    {
        return Ansi::color($string, self::$colors['access']);
    }

    public static function property(string $string): string
    {
        return Ansi::color($string, self::$colors['property']);
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
        $dirName = self::normalizePath(dirname($file));
        $fileName = basename($file);
        $separator = $dirName ? (Str::contains($file, '://') ? '//' : '/') : '';

        foreach (self::$trimPathPrefix as $prefix) {
            if (Str::startsWith($dirName, $prefix)) {
                $dirName = substr($dirName, strlen($prefix));
                break;
            }
        }

        return Ansi::color($dirName . $separator, self::$colors['path'])
            . Ansi::color($fileName, self::$colors['file']);
    }

    public static function fileLine(string $file, int $line): string
    {
        $dirName = self::normalizePath(dirname($file)) . '/';
        $fileName = basename($file);

        foreach (self::$trimPathPrefix as $prefix) {
            if (Str::startsWith($dirName, $prefix)) {
                $dirName = substr($dirName, strlen($prefix));
                break;
            }
        }

        return Ansi::color($dirName, self::$colors['path'])
            . Ansi::color($fileName, self::$colors['file'])
            . Ansi::color(':', self::$colors['info'])
            . Ansi::color((string) $line, self::$colors['line']);
    }

    public static function url(string $url): string
    {
        $url = (string) preg_replace('/([a-zA-Z0-9_-]+)=/', Ansi::dyellow('$1') . '=', $url);
        $url = (string) preg_replace('/=([a-zA-Z0-9_-]+)/', '=' . Ansi::lcyan('$1'), $url);
        $url = (string) preg_replace('/[\\/?&=]/', Ansi::dgray('$0'), $url);

        return $url;
    }

    /**
     * @param array<int|string|null> $params
     * @param int|string|mixed[]|bool|null $return
     */
    public static function call(string $name, array $params = [], $return = null/*, array $hints = []*/): string
    {
        $info = Dumper::$showInfo;
        Dumper::$showInfo = null;

        $formatted = [];
        foreach ($params as $key => $value) {
            $key = is_int($key) ? null : $key;
            $formatted[] = Dumper::dumpValue($value, 0, $key);
        }
        $params = implode(Ansi::color(', ', Dumper::$colors['function']), $formatted);

        if ($return === null) {
            $output = '';
            $end = ')';
        } elseif (is_scalar($return) || is_resource($return)) {
            $output = ' ' . Dumper::dumpValue($return);
            $end = '):';
        } elseif (is_array($return)) {
            $output = [];
            foreach ($return as $k => $v) {
                if (is_int($k)) {
                    $output[] = Dumper::dumpValue($v);
                } else {
                    $output[] = Ansi::color($k . ':', Dumper::$colors['function']) . ' ' . Dumper::dumpValue($v);
                }
            }
            $output = ' ' . implode(' ', $output);
            $end = '):';
        } else {
            $output = ' ' . Dumper::dumpValue($return);
            $end = '):';
        }

        Dumper::$showInfo = $info;

        return self::func($name . '(', $params, $end, $output);
    }

    public static function func(string $name, string $params = '', string $end = '', string $return = ''): string
    {
        if ($params || $end || $return) {
            return Ansi::color($name, Dumper::$colors['function']) . $params . Ansi::color($end, Dumper::$colors['function']) . $return;
        } else {
            return Ansi::color($name, Dumper::$colors['function']);
        }
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

        $binary = self::escapeAsBinary($string, array_keys(self::getTranslations(self::$stringsEscaping)));
        $split = false;
        if ($splitBy !== null) {
            $pos = strpos($string, $splitBy);
            if ($pos !== false && $pos !== strlen($string)) {
                $split = true;
            }
        }

        $escaping = $binary ? self::$binaryEscaping : self::$stringsEscaping;
        $translations = self::getTranslations($escaping);
        $pattern = self::createCharPattern(array_keys($translations));

        if (!self::$escapeWhiteSpace) {
            unset($translations["\n"], $translations["\r"], $translations["\t"]);
        }

        if ((!$binary && !$split) || $depth === null || self::$binaryChunkLength === null || $length <= self::$binaryChunkLength) {
            // not chunked (one chunk)
            return self::stringChunk($string, $escaping, $pattern, $translations, false, $ellipsis);
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
        foreach ($chunks as $i => $chunk) {
            $e = $i === count($chunks) - 1 ? $ellipsis : '';
            $chunks[$i] = self::stringChunk($chunk, $escaping, $pattern, $translations, $binary, $e);
        }
        $sep = "\n" . self::indent($depth) . ' ' . self::symbol('.') . ' ';
        $prefix = $binary ? self::exceptions('binary:') . "\n" . self::indent($depth) . '   ' : '';

        return $prefix . implode($sep, $chunks);
    }

    /**
     * @param string[] $translations
     */
    private static function stringChunk(
        string $string,
        int $escaping,
        string $pattern,
        array $translations,
        bool $binary,
        string $ellipsis = ''
    ): string
    {
        $quote = '"';

        if ($escaping === self::ESCAPING_NONE) {
            $formatted = Ansi::color($quote . $string . $ellipsis . $quote, self::$colors['string']);
        } elseif ($escaping === self::ESCAPING_CP437) {
            $formatted = Ansi::color($quote . Cp437::toUtf8Printable($string) . $ellipsis . $quote, self::$colors['string']);
        } else {
            $formatted = preg_replace_callback($pattern, static function (array $m) use ($escaping, $translations): string {
                $ch = $m[0];
                if (isset($translations[$ch])) {
                    $ch = $translations[$ch];
                } else {
                    $ch = $escaping === self::ESCAPING_JSON
                        ? '\u00' . Str::charToHex($ch)
                        : '\x' . Str::charToHex($ch);
                }

                return Ansi::between($ch, self::$colors['escape'], self::$colors['string']);
            }, $string);
            if (self::$escapeAllNonAscii && $escaping !== self::ESCAPING_MYSQL) {
                $formatted = preg_replace_callback('~[\x80-\x{10FFFF}]~u', static function (array $m) use ($escaping): string {
                    $ch = $m[0];
                    if ($escaping === self::ESCAPING_JS || $escaping === self::ESCAPING_JSON) {
                        /** @var string $code */
                        $code = json_encode($ch);
                        $ch = trim($code, '"');
                    } else {
                        $ch = '\u{' . dechex(Str::ord($ch)) . '}';
                    }

                    return Ansi::between($ch, self::$colors['escape'], self::$colors['string']);
                }, $formatted);
            }

            $formatted = Ansi::color($quote . $formatted . $ellipsis . $quote, self::$colors['string']);
        }

        // hexadecimal
        if ($binary && self::$binaryWithHexadecimal) {
            $formatted .= ' ' . self::info('// ' . Str::strToHex($string));
        }

        return $formatted;
    }

    /**
     * @param string[] $allowedChars
     */
    private static function escapeAsBinary(string $string, array $allowedChars = []): bool
    {
        if ($allowedChars !== []) {
            $chars = array_diff(range("\x00", "\x1f"), $allowedChars);
            $pattern = self::createCharPattern($chars);
        } else {
            $pattern = '~[\x00-\x1f]~';
        }

        return preg_match($pattern, $string) === 1;
    }

    /**
     * @param string[] $chars
     */
    private static function createCharPattern(array $chars): string
    {
        $chars = array_map(static function (string $ch): string {
            return '\x' . Str::charToHex($ch);
        }, $chars);

        return '~[' . implode('', $chars) . ']~';
    }

    /**
     * @return string[]
     */
    private static function getTranslations(int $escaping): array
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
        }

        return $translations;
    }

}
