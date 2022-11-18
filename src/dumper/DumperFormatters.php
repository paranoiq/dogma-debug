<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable Squiz.Arrays.ArrayDeclaration.IndexNoNewline

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
use function array_keys;
use function array_map;
use function array_pop;
use function array_reverse;
use function array_slice;
use function array_values;
use function basename;
use function bin2hex;
use function chr;
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
use function ord;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function property_exists;
use function rtrim;
use function spl_object_hash;
use function spl_object_id;
use function str_contains;
use function str_ends_with;
use function str_pad;
use function str_repeat;
use function str_replace;
use function str_split;
use function stream_context_get_params;
use function stream_get_meta_data;
use function strlen;
use function strpos;
use function strrev;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;
use const PATH_SEPARATOR;
use const PHP_VERSION_ID;
use const STR_PAD_LEFT;

trait DumperFormatters
{

    /** @var array<string, string> */
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

    // objects and resources -------------------------------------------------------------------------------------------

    /**
     * @param object $object
     */
    public static function dumpEntityId($object): ?string
    {
        if (!property_exists($object, 'id')) {
            return null;
        }

        $ref = new ReflectionObject($object);
        $property = $ref->getProperty('id');
        $property->setAccessible(true);

        $id = self::dumpValue($property->getValue($object));
        $access = $property->isPrivate() ? 'private' : ($property->isProtected() ? 'protected' : 'public');
        $info = self::$showInfo ? ' ' . self::info('// #' . self::objectHash($object)) : '';

        return Dumper::name(get_class($object)) . self::bracket('(')
            . self::access($access) . ' ' . self::property('id') . ' ' . self::symbol('=') . ' ' . self::value($id)
            . ' ' . self::exceptions('...') . ' ' . self::bracket(')') . $info;
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

    public static function dumpIntTime(int $int): ?string
    {
        if ($int >= 10000000) {
            return self::int((string) $int) . ' ' . self::info('// ' . self::intToFormattedDate($int));
        }

        return null;
    }

    public static function dumpFloatTime(float $float): ?string
    {
        $decimal = (float) (int) $float === $float ? '.0' : '';

        if ($float >= 1000000) {
            /** @var DateTime $time */
            $time = DateTime::createFromFormat('U.uP', $float . $decimal . 'Z');
            $time = $time->setTimezone(self::getTimeZone())->format('Y-m-d H:i:s.uP');

            return self::float((string) $float) . ' ' . self::info('// ' . $time);
        } elseif ($float <= 3600) {
            return self::float((string) $float) . ' ' . self::info('// ' . Units::time($float));
        }

        return null;
    }

    public static function dumpIntPermissions(int $int): ?string
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

    public static function dumpIntSize(int $int): ?string
    {
        if ($int < 1024) {
            return null;
        }

        return self::int((string) $int) . ' ' . self::info('// ' . Units::memory($int));
    }

    public static function dumpIntFlags(int $int): ?string
    {
        if ($int < 0) {
            return null;
        }

        $info = implode('|', array_reverse(self::binaryComponents($int)));

        return self::int((string) $int) . ' ' . self::info('// ' . $info);
    }

    public static function dumpIntPowersOfTwo(int $int): ?string
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

    public static function dumpStringHidden(string $string, string $info, string $key, int $depth): ?string
    {
        $key2 = ltrim($key, '$');
        if (!in_array($key, self::$hiddenFields, true) && !in_array($key2, self::$hiddenFields, true)) {
            return null;
        }

        $quote = Ansi::color('"', self::$colors['string']);

        if (str_ends_with($info, ', trimmed')) {
            $info = substr($info, 0, -9);
        }
        $info .= $info ? ', hidden' : 'hidden';
        $info = ' ' . self::info("// $info");

        return $quote . self::exceptions('*****') . $quote . $info;
    }

    public static function dumpStringPathList(string $string, string $info, string $key, int $depth): ?string
    {
        if (!str_contains($string, PATH_SEPARATOR)) {
            return null;
        }

        return self::string($string, $depth, PATH_SEPARATOR) . ' ' . self::info("// $info");
    }

    public static function dumpStringPath(string $string, string $info, string $key, int $depth): ?string
    {
        if (preg_match('~file|path~', $key)
            || preg_match('~^[a-z]:[/\\\\]~i', $string)
            || ($string !== '' && $string[0] === '/')
            || str_contains($string, '/../')
        ) {
            $path = self::normalizePath($string);
            $path = ($path !== rtrim($string, '/')) ? ', ' . $path : '';

            return self::string($string, $depth) . ' ' . self::info("// $info{$path}");
        }

        return null;
    }

    public static function dumpStringUuid(string $string, string $info, string $key, int $depth): ?string
    {
        static $uuidRe = '~(?:urn:uuid:)?{?([0-9a-f]{8})-?([0-9a-f]{4})-?([0-9a-f]{4})-?([0-9a-f]{4})-?([0-9a-f]{12})}?~';

        $bytes = strlen($string);
        // phpcs:disable SlevomatCodingStandard.ControlStructures.AssignmentInCondition.AssignmentInCondition
        if ((preg_match($uuidRe, $string, $m) && $uuidInfo = self::uuidInfo(array_slice($m, 1)))
            || ($bytes === 32 && preg_match('~id$~i', $key) && $uuidInfo = self::binaryUuidInfo($string))
        ) {
            return self::string($string, $depth) . ' ' . self::info('// ' . $uuidInfo);
        }

        return null;
    }

    public static function dumpStringColor(string $string, string $info, string $key, int $depth): ?string
    {
        if (!Ansi::isColor($string, !preg_match('~color|background~i', $key))) {
            return null;
        }

        return self::string($string, $depth) . ' ' . self::info("// " . Ansi::rgb('     ', null, $string));
    }

    public static function dumpStringCallable(string $string, string $info, string $key, int $depth): ?string
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
        $under = self::$numbersWithUnderscore ?? (PHP_VERSION_ID >= 70400);
        if ($under) {
            $value = strrev(implode('_', str_split(strrev($value), 3)));
        }

        return Ansi::color($value, self::$colors['int']);
    }

    public static function float(string $value): string
    {
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
        if ($key === '' || (is_string($key) && (Str::isBinary($key) || preg_match('~\\s~', $key) !== 0))) {
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

        $escaping = $binary ? self::$binaryEscaping : self::$stringsEscaping;
        $translations = self::getTranslations($escaping);
        if (!self::$escapeWhiteSpace) {
            unset($translations["\n"], $translations["\r"], $translations["\t"]);
        }
        $translationsWithoutQuote = $translations;
        unset($translationsWithoutQuote['"']);
        $apos = false;
        if ($escaping === self::ESCAPING_PHP || $escaping === self::ESCAPING_JS) {
            if (str_replace(array_keys($translationsWithoutQuote), '', $string) === $string
                && str_replace('"', '', $string) !== $string
                && str_replace("'", '', $string) === $string
            ) {
                $apos = true;
                $translations = $translationsWithoutQuote;
            }
        }
        $pattern = Str::createCharPattern(array_keys($translations));

        if ((!$binary && !$split) || $depth === null || self::$binaryChunkLength === null || $length <= self::$binaryChunkLength) {
            // not chunked (one chunk)
            return self::stringChunk(-1, $string, $escaping, $pattern, $translations, false, $ellipsis, $apos ? "'" : '"');
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
        bool $binary,
        string $ellipsis = '',
        string $quote = '"',
        int $offsetChars = 1
    ): string
    {
        if ($escaping === self::ESCAPING_NONE) {
            $formatted = Ansi::color($quote . $string . $ellipsis . $quote, self::$colors['string']);
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
        }, $string);

        if (self::$escapeAllNonAscii && $escaping !== self::ESCAPING_MYSQL) {
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
