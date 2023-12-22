<?php
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
use mysqli;
use ReflectionException;
use ReflectionFunction;
use ReflectionObject;
use UnitEnum;
use WeakReference;
use function abs;
use function array_reverse;
use function array_slice;
use function decoct;
use function fread;
use function ftell;
use function get_class;
use function get_extension_funcs;
use function get_loaded_extensions;
use function implode;
use function in_array;
use function is_callable;
use function is_int;
use function json_decode;
use function json_encode;
use function ltrim;
use function preg_match;
use function proc_get_status;
use function property_exists;
use function rewind;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_pad;
use function str_replace;
use function str_starts_with;
use function stream_context_get_params;
use function stream_get_meta_data;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use const JSON_PRETTY_PRINT;
use const PATH_SEPARATOR;
use const STR_PAD_LEFT;

class FormattersDefault
{

    /**
     * @param object $object
     */
    public static function dumpEntityId($object, int $depth = 0): ?string
    {
        if (!property_exists($object, 'id')) {
            return null;
        }

        $ref = new ReflectionObject($object);
        $property = $ref->getProperty('id');
        $property->setAccessible(true);

        $id = Dumper::dumpValue($property->getValue($object), $depth + 1);
        $access = $property->isPrivate() ? 'private' : ($property->isProtected() ? 'protected' : 'public');
        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($object)) : '';

        return Dumper::class(get_class($object)) . Dumper::bracket('(')
            . Dumper::access($access) . ' ' . Dumper::property('id') . ' ' . Dumper::symbol('=') . ' ' . Dumper::value($id)
            . ' ' . Dumper::exceptions('...') . ' ' . Dumper::bracket(')') . $info;
    }

    /**
     * @param resource $resource
     */
    public static function dumpStream($resource, int $depth = 0): string
    {
        $id = (int) $resource;

        $metadata = stream_get_meta_data($resource);
        $position = ftell($resource);
        $metadata['position'] = $position;
        if ($metadata['seekable'] && $position !== false && Dumper::$dumpContentsOfSeekableStreams) {
            if ($position !== 0) {
                rewind($resource);
            }
            $contents = fread($resource, 8192);
            $metadata['contents'] = $contents;
            fseek($resource, $position);
        }

        return Dumper::resource("(stream {$id})") . ' ' . Dumper::bracket('{')
            . Dumper::dumpVariables($metadata, $depth)
            . Dumper::indent($depth) . Dumper::bracket('}');
    }

    /**
     * @param resource $resource
     */
    public static function dumpStreamContext($resource, int $depth = 0): string
    {
        $id = (int) $resource;

        $params = stream_context_get_params($resource);
        if ($params !== ['options' => []]) {
            $params = Dumper::dumpVariables($params, $depth) . Dumper::indent($depth);

            return Dumper::resource("(stream-context {$id})") . ' ' . Dumper::bracket('{')
                . $params . Dumper::bracket('}');
        } else {
            return Dumper::resource('(stream-context)') . ' ' . Dumper::info('#' . (int) $resource);
        }
    }

    /**
     * @param resource $resource
     */
    public static function dumpProcess($resource, int $depth = 0): string
    {
        $id = (int) $resource;

        $params = proc_get_status($resource);
        if ($params !== ['options' => []]) {
            $params = Dumper::dumpVariables($params, $depth) . Dumper::indent($depth);

            return Dumper::resource("(process {$id})") . ' ' . Dumper::bracket('{')
                . $params . Dumper::bracket('}');
        } else {
            return Dumper::resource('(process)') . ' ' . Dumper::info('#' . (int) $resource);
        }
    }

    public static function dumpUnitEnum(UnitEnum $enum): string
    {
        return Dumper::class(get_class($enum)) . Dumper::symbol('::') . Dumper::case($enum->name);
    }

    public static function dumpBackedEnum(BackedEnum $enum): string
    {
        $value = is_int($enum->value) ? Dumper::int((string) $enum->value) : Dumper::string($enum->value);

        return Dumper::class(get_class($enum)) . Dumper::symbol('::') . Dumper::case($enum->name)
            . Dumper::bracket('(') . $value . Dumper::bracket(')');
    }

    public static function dumpWeakReference(WeakReference $weakReference, int $depth = 0): string
    {
        $object = $weakReference->get();

        return Dumper::class(get_class($weakReference)) . Dumper::bracket('(')
            . Dumper::dumpValue($object, $depth /* no increment */) . Dumper::bracket(')');
    }

    public static function dumpCallstack(Callstack $callstack, int $depth = 0): string
    {
        return Dumper::class(get_class($callstack)) . ' ' . Dumper::dumpValue($callstack->frames, $depth /* no increment */);
    }

    public static function dumpDateTimeInterface(DateTimeInterface $dt): string
    {
        $value = str_replace('.000000', '', $dt->format('Y-m-d H:i:s.u'));
        $timeZone = $dt->format('P') === $dt->getTimezone()->getName() ? '' : ' ' . Dumper::value($dt->getTimezone()->getName());
        $dst = $dt->format('I') ? ' ' . Dumper::value2('DST') : '';
        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($dt)) : '';

        return Dumper::class(get_class($dt)) . Dumper::bracket('(')
            . Dumper::value($value) . Dumper::value2($dt->format('P')) . $timeZone . $dst
            . Dumper::bracket(')') . $info;
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
        $info = Dumper::$showInfo ? ' ' . Dumper::info('// #' . Dumper::objectHash($mysqli)) : '';

        return Dumper::class(get_class($mysqli)) . ' ' . Dumper::bracket('{')
            . Dumper::dumpVariables($properties, $depth + 1) . Dumper::bracket('}') . $info;
    }

    public static function dumpIntTime(int $int): ?string
    {
        if ($int >= 10000000) {
            return Dumper::int((string) $int) . ' ' . Dumper::info('// ' . Dumper::intToFormattedDate($int));
        }

        return null;
    }

    public static function dumpFloatTime(float $float): ?string
    {
        $decimal = (float) (int) $float === $float ? '.0' : '';

        if ($float >= 1000000) {
            /** @var DateTime $time */
            $time = DateTime::createFromFormat('U.uP', $float . $decimal . 'Z');
            $time = $time->setTimezone(Dumper::getTimeZone())->format('Y-m-d H:i:s.uP');

            return Dumper::float($float) . ' ' . Dumper::info('// ' . $time);
        } elseif ($float <= 3600) {
            return Dumper::float($float) . ' ' . Dumper::info('// ' . Units::time($float));
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

        return Dumper::int(str_pad(decoct($int), 4, '0', STR_PAD_LEFT)) . ' ' . Dumper::info('// ' . $perms);
    }

    public static function dumpIntSize(int $int): ?string
    {
        if ($int < 1024) {
            return null;
        }

        return Dumper::int((string) $int) . ' ' . Dumper::info('// ' . Units::memory($int));
    }

    public static function dumpIntFlags(int $int): ?string
    {
        if ($int < 0) {
            return null;
        }

        $info = implode('|', array_reverse(Dumper::binaryComponents($int)));

        return Dumper::int((string) $int) . ' ' . Dumper::info('// ' . $info);
    }

    public static function dumpIntHttpCode(int $int): ?string
    {
        if (!isset(Http::RESPONSE_MESSAGES[$int])) {
            return null;
        }

        return Dumper::int((string) $int) . ' ' . Dumper::info('// ' . Http::RESPONSE_MESSAGES[$int]);
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

        return Dumper::int((string) $int) . ' ' . Dumper::info("// 2^{$exp}");
    }

    public static function dumpStringHidden(string $string, string $info, string $key, int $depth): ?string
    {
        $key2 = ltrim($key, '$');
        if (!in_array($key, Dumper::$hiddenFields, true) && !in_array($key2, Dumper::$hiddenFields, true)) {
            return null;
        }

        $quote = Ansi::color('"', Dumper::$colors['string']);

        if (str_ends_with($info, ', trimmed')) {
            $info = substr($info, 0, -9);
        }
        $info .= $info ? ', hidden' : 'hidden';
        $info = ' ' . Dumper::info("// {$info}");

        return $quote . Dumper::exceptions('*****') . $quote . $info;
    }

    public static function dumpStringPathList(string $string, string $info, string $key, int $depth): ?string
    {
        if (!str_contains($string, PATH_SEPARATOR)) {
            return null;
        }

        return Dumper::string($string, $depth, PATH_SEPARATOR) . ' ' . Dumper::info("// {$info}");
    }

    public static function dumpStringPath(string $string, string $info, string $key, int $depth): ?string
    {
        if (preg_match('~file|path~', $key)
            || preg_match('~^[a-z]:[/\\\\]~i', $string)
            || ($string !== '' && $string[0] === '/')
            || str_contains($string, '/../')
        ) {
            $path = Dumper::normalizePath($string);
            $path = ($path !== rtrim($string, '/')) ? ', ' . $path : '';

            return Dumper::string($string, $depth) . ' ' . Dumper::info("// {$info}{$path}");
        }

        return null;
    }

    public static function dumpStringUuid(string $string, string $info, string $key, int $depth): ?string
    {
        static $uuidRe = '~^(?:urn:uuid:)?{?([0-9a-f]{8})-?([0-9a-f]{4})-?([0-9a-f]{4})-?([0-9a-f]{4})-?([0-9a-f]{12})}?$~';

        $bytes = strlen($string);
        // phpcs:disable SlevomatCodingStandard.ControlStructures.AssignmentInCondition.AssignmentInCondition
        if ((preg_match($uuidRe, $string, $m) && $uuidInfo = Dumper::uuidInfo(array_slice($m, 1)))
            || ($bytes === 32 && preg_match('~id$~i', $key) && $uuidInfo = Dumper::binaryUuidInfo($string))
        ) {
            return Dumper::string($string, $depth) . ' ' . Dumper::info('// ' . $uuidInfo);
        }

        return null;
    }

    public static function dumpStringColor(string $string, string $info, string $key, int $depth): ?string
    {
        if (!Color::isColor($string, !preg_match('~color|background~i', $key))) {
            return null;
        }

        return Dumper::string($string, $depth) . ' ' . Dumper::info("// " . Ansi::rgb('     ', null, $string));
    }

    public static function dumpStringCallable(string $string, string $info, string $key, int $depth): ?string
    {
        if (!is_callable($string)) {
            return null;
        }

        try {
            $ref = new ReflectionFunction($string);
        } catch (ReflectionException $e) {
            return null;
        }

        $info .= $info ? ', ' : '';
        if ($ref->isUserDefined()) {
            $file = str_replace('\\', '/', $ref->getFileName() ?: '');
            $line = $ref->getStartLine();

            // todo: trim file prefix
            $info .= "callable defined in {$file}:{$line}";

            return Dumper::string($string, $depth) . ' ' . Dumper::info("// {$info}");
        } else {
            foreach (get_loaded_extensions() as $extension) {
                if (in_array($string, get_extension_funcs($extension) ?: [], true)) {
                    $extension = strtolower($extension);
                    $info .= "callable from ext-{$extension}";

                    return Dumper::string($string, $depth) . ' ' . Dumper::info("// {$info}");
                }
            }

            $info .= "callable";

            return Dumper::string($string, $depth) . ' ' . Dumper::info("// {$info}");
        }
    }

    public static function dumpStringJson(string $string, string $info, string $key, int $depth): ?string
    {
        if (Dumper::$jsonStrings === Dumper::JSON_KEEP_AS_IS) {
            return null;
        }

        $trimmed = trim($string);
        if (!str_starts_with($trimmed, '{') && !str_starts_with($trimmed, '[')) {
            return null;
        }

        $data = json_decode($string, true);
        if ($data === null) {
            return null;
        }
        if (Dumper::$jsonStrings === Dumper::JSON_DECODE) {
            return Dumper::exceptions('json:') . ' ' . Dumper::dump($data) . ' ' . Dumper::info("// {$info}");
        }

        $json = json_encode($data, JSON_PRETTY_PRINT);

        return Dumper::exceptions('prettified:') . ' ' . $json . ' ' . Dumper::info("// {$info}");
    }

}
