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
use mysqli_result;
use mysqli_stmt;
use OuterIterator;
use ReflectionException;
use ReflectionFunction;
use ReflectionObject;
use UnitEnum;
use WeakReference;
use function abs;
use function array_filter;
use function array_reverse;
use function array_slice;
use function decoct;
use function fread;
use function fseek;
use function ftell;
use function get_class;
use function get_extension_funcs;
use function get_loaded_extensions;
use function implode;
use function in_array;
use function intval;
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

    /** @var array<int, int> */
    private static $processExitCodes = [];

    /**
     * @param object $object
     */
    public static function dumpEntityId($object, DumperConfig $config, int $depth = 0): ?string
    {
        if (!property_exists($object, 'id')) {
            return null;
        }

        $ref = new ReflectionObject($object);
        $property = $ref->getProperty('id');
        $property->setAccessible(true);

        $id = Dumper::dumpValue($property->getValue($object), $config, $depth + 1);
        $access = $property->isPrivate() ? 'private' : ($property->isProtected() ? 'protected' : 'public');

        return Dumper::class(get_class($object), $config) . Dumper::bracket('(')
            . Dumper::access($access) . ' ' . Dumper::property('id') . ' ' . Dumper::symbol('=') . ' ' . Dumper::value($id)
            . ' ' . Dumper::exceptions('...') . ' ' . Dumper::bracket(')') . Dumper::objectHashInfo($object);
    }

    /**
     * @param resource $resource
     */
    public static function dumpStream($resource, DumperConfig $config, int $depth = 0): string
    {
        $id = (int) $resource;

        $metadata = stream_get_meta_data($resource);
        $position = ftell($resource);
        $metadata['position'] = $position;
        $contents = null;
        if ($metadata['seekable'] && $position !== false && Dumper::$config->dumpContentsOfSeekableStreams) {
            if ($position !== 0) {
                rewind($resource);
            }
            $contents = fread($resource, 8192);
            fseek($resource, $position);
        }

        $result = Dumper::resource("(stream {$id}");
        $result .= ' ' . Dumper::value($metadata['stream_type']) . ' ' . Dumper::value2($metadata['mode']);
        $result .= ' ' . Dumper::value($metadata['seekable'] ? 'seekable' : 'non-seekable');
        $result .= ' ' . Dumper::value(!empty($metadata['blocked']) ? 'blocked' : 'non-blocked');
        if (!empty($metadata['timed_out'])) {
            $result .= ' ' . Dumper::value('timed-out');
        }
        if ($metadata['position'] !== false) {
            $result .= ' position:' . Dumper::int($metadata['position'], $config);
        }
        if ($metadata['unread_bytes'] !== 0) {
            $result .= ' unread-bytes:' . Dumper::int($metadata['unread_bytes'], $config);
        }
        if (!empty($metadata['eof'])) {
            $result .= ' ' . Dumper::value2('EOF');
        }
        if ($contents !== null) {
            $result .= ' ' . Dumper::string($contents, $config);
        }
        $result .= Dumper::resource(")");

        return $result;
    }

    /**
     * @param resource $resource
     */
    public static function dumpStreamContext($resource, DumperConfig $config, int $depth = 0): string
    {
        $id = (int) $resource;

        $params = stream_context_get_params($resource);
        if ($params !== ['options' => []]) {
            $params = Dumper::dumpVariables($params, $config, $depth) . Dumper::indent($depth, $config);

            return Dumper::resource("(stream-context {$id})") . ' ' . Dumper::bracket('{')
                . $params . Dumper::bracket('}');
        } else {
            return Dumper::resource('(stream-context)') . ' ' . Dumper::info('#' . (int) $resource);
        }
    }

    /**
     * @param resource $resource
     */
    public static function dumpProcess($resource, DumperConfig $config, int $depth = 0): string
    {
        $id = intval($resource);

        $params = proc_get_status($resource);
        if ($params !== ['options' => []]) {
            $result = Dumper::resource("(process {$id}");
            if ($params['pid']) {
                $result .= ' pid:' . Dumper::int($params['pid'], $config);
            }
            $result .= ' ' . Dumper::string($params['command'], $config);

            // should be mutually exclusive, but who knows...
            if ($params['running']) {
                $result .= ' ' . Dumper::value('running');
            }
            if ($params['signaled']) {
                $result .= ' ' . Dumper::value('terminated(') . Dumper::int($params['termsig'], $config) . Dumper::value(')');
            }
            if ($params['stopped']) {
                $result .= ' ' . Dumper::value('stopped(') . Dumper::int($params['stopsig'], $config) . Dumper::value(')');
            }
            if (!$params['running'] && !$params['signaled'] && !$params['stopped']) {
                $result .= ' ' . Dumper::value('ended');
            }

            // cache exitcode because "Only first call of this function return real value, next calls return -1."
            if (isset(self::$processExitCodes[$id])) {
                $params['exitcode'] = self::$processExitCodes[$id];
            }
            if ($params['exitcode'] !== -1) {
                $result .= ' exit:' . Dumper::int($params['exitcode'], $config);
                self::$processExitCodes[$id] = $params['exitcode'];
            }
            $result .= Dumper::resource(")");
        } else {
            $result = Dumper::resource("(process {$id})");
        }

        return $result;
    }

    /**
     * @param resource $resource
     */
    public static function dumpClosedProcess($resource, DumperConfig $config, int $depth = 0): string
    {
        $id = (int) $resource;
        unset(self::$processExitCodes[$id]);

        return Dumper::resource("(closed {$id})");
    }

    public static function dumpUnitEnum(UnitEnum $enum, DumperConfig $config, int $depth = 0): string
    {
        return Dumper::class(get_class($enum), $config) . Dumper::symbol('::') . Dumper::case($enum->name);
    }

    public static function dumpBackedEnum(BackedEnum $enum, DumperConfig $config, int $depth = 0): string
    {
        $value = is_int($enum->value) ? Dumper::int((string) $enum->value, $config) : Dumper::string($enum->value, $config);

        return Dumper::class(get_class($enum), $config) . Dumper::symbol('::') . Dumper::case($enum->name)
            . Dumper::bracket('(') . $value . Dumper::bracket(')');
    }

    public static function dumpWeakReference(WeakReference $weakReference, DumperConfig $config, int $depth = 0): string
    {
        $object = $weakReference->get();

        return Dumper::class(get_class($weakReference), $config) . Dumper::bracket('(')
            . Dumper::dumpValue($object, $config, $depth /* no increment */) . Dumper::bracket(')');
    }

    public static function dumpCallstack(Callstack $callstack, DumperConfig $config, int $depth = 0): string
    {
        return Dumper::class(get_class($callstack), $config) . ' ' . Dumper::dumpValue($callstack->frames, $config, $depth + 1);
    }

    public static function dumpDateTimeInterface(DateTimeInterface $dt, DumperConfig $config, int $depth = 0): string
    {
        $value = str_replace('.000000', '', $dt->format('Y-m-d H:i:s.u'));
        $timeZone = $dt->format('P') === $dt->getTimezone()->getName() ? '' : ' ' . Dumper::value($dt->getTimezone()->getName());
        $dst = $dt->format('I') ? ' ' . Dumper::value2('DST') : '';

        return Dumper::class(get_class($dt), $config) . Dumper::bracket('(')
            . Dumper::value($value) . Dumper::value2($dt->format('P')) . $timeZone . $dst
            . Dumper::bracket(')') . Dumper::objectHashInfo($dt);
    }

    public static function dumpOuterIterator(OuterIterator $iterator, DumperConfig $config, int $depth = 0): string
    {
        return Dumper::class(get_class($iterator), $config) . Dumper::bracket(' {') . "\n"
            . Dumper::indent($depth + 1, $config)
            . Dumper::dumpValue($iterator->getInnerIterator(), $config, $depth + 1) . "\n"
            . Dumper::bracket('}') . Dumper::objectHashInfo($iterator);
    }

    public static function dumpMysqli(mysqli $mysqli, DumperConfig $config, int $depth = 0): string
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

        return Dumper::class(get_class($mysqli), $config) . ' ' . Dumper::bracket('{')
            . Dumper::dumpVariables($properties, $config, $depth) . Dumper::indent($depth, $config)
            . Dumper::bracket('}') . Dumper::objectHashInfo($mysqli);
    }

    public static function dumpMysqliStatement(mysqli_stmt $statement, DumperConfig $config, int $depth = 0): string
    {
        $properties = [
            'affected_rows' => $statement->affected_rows,
            'insert_id' => $statement->insert_id,
            'num_rows' => $statement->num_rows,
            'param_count' => $statement->param_count,
            'field_count' => $statement->field_count,
            'errno' => $statement->errno,
            'error' => $statement->error,
            'error_list' => $statement->error_list,
            'sqlstate' => $statement->sqlstate,
            'id' => $statement->id,
        ];
        $properties = array_filter($properties);
        if ($properties['sqlstate'] === '00000') {
            unset($properties['sqlstate']);
        }

        return Dumper::class(get_class($statement), $config) . ' ' . Dumper::bracket('{')
            . Dumper::dumpVariables($properties, $config, $depth)
            . Dumper::bracket('}') . Dumper::objectHashInfo($statement);
    }

    public static function dumpMysqliResult(mysqli_result $result, DumperConfig $config, int $depth): string
    {
        $properties = [
            'current_field' => $result->current_field,
            'field_count' => $result->field_count,
            'lengths' => $result->lengths,
            'type' => $result->type,
        ];
        $properties = array_filter($properties);
        $properties['num_rows'] = $result->num_rows;

        return Dumper::class(get_class($result), $config) . ' ' . Dumper::bracket('{')
            . Dumper::dumpVariables($properties, $config, $depth) . Dumper::indent($depth, $config)
            . Dumper::bracket('}') . Dumper::objectHashInfo($result);
    }

    public static function dumpIntTime(int $int, DumperConfig $config): ?string
    {
        if ($int >= 10000000) {
            return Dumper::int((string) $int, $config) . ' ' . Dumper::info('// ' . Dumper::timestampToFormattedDate($int, $config));
        }

        return null;
    }

    public static function dumpFloatTime(float $float, DumperConfig $config): ?string
    {
        $decimal = (float) (int) $float === $float ? '.0' : '';

        if ($float >= 1000000) {
            /** @var DateTime $time */
            $time = DateTime::createFromFormat('U.uP', $float . $decimal . 'Z');
            $time = $time->setTimezone(Dumper::getTimeZone($config))->format('Y-m-d H:i:s.uP');

            return Dumper::float($float, $config) . ' ' . Dumper::info('// ' . $time);
        } elseif ($float <= 3600) {
            return Dumper::float($float, $config) . ' ' . Dumper::info('// ' . Units::time($float));
        }

        return null;
    }

    public static function dumpIntPermissions(int $int, DumperConfig $config): ?string
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

        return Dumper::int(str_pad(decoct($int), 4, '0', STR_PAD_LEFT), $config) . ' ' . Dumper::info('// ' . $perms);
    }

    public static function dumpIntSignal(int $int, DumperConfig $config): ?string
    {
        if ($int <= 0) {
            return null;
        }

        $name = Signals::getSignalName($int);

        return Dumper::int((string) $int, $config) . ' ' . Dumper::info('// ' . $name);
    }

    public static function dumpIntExitCode(int $int, DumperConfig $config): ?string
    {
        if ($int <= 0) {
            return null;
        }

        $name = ExecInterceptor::getExitCodeName($int);

        return Dumper::int((string) $int, $config) . ' ' . Dumper::info('// ' . $name);
    }

    public static function dumpIntSize(int $int, DumperConfig $config): ?string
    {
        if ($int < 1024) {
            return null;
        }

        return Dumper::int((string) $int, $config) . ' ' . Dumper::info('// ' . Units::memory($int));
    }

    public static function dumpIntFlags(int $int, DumperConfig $config): ?string
    {
        if ($int < 0) {
            return null;
        }

        $info = implode('|', array_reverse(Dumper::binaryComponents($int)));

        return Dumper::int((string) $int, $config) . ' ' . Dumper::info('// ' . $info);
    }

    public static function dumpIntHttpCode(int $int, DumperConfig $config): ?string
    {
        if (!isset(Http::RESPONSE_MESSAGES[$int])) {
            return null;
        }

        return Dumper::int((string) $int, $config) . ' ' . Dumper::info('// ' . Http::RESPONSE_MESSAGES[$int]);
    }

    public static function dumpIntPowersOfTwo(int $int, DumperConfig $config): ?string
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

        return Dumper::int((string) $int, $config) . ' ' . Dumper::info("// 2^{$exp}");
    }

    public static function dumpIntMysqlCharset(int $int, DumperConfig $config): ?string
    {
        if (!isset(MysqlResultInfo::CHARACTER_SETS[$int])) {
            return null;
        }

        return Dumper::int((string) $int, $config) . ' ' . Dumper::info('// ' . MysqlResultInfo::CHARACTER_SETS[$int]);
    }

    public static function dumpIntMysqlType(int $int, DumperConfig $config): ?string
    {
        if (!isset(MysqlResultInfo::DATA_TYPES[$int])) {
            return null;
        }

        return Dumper::int((string) $int, $config) . ' ' . Dumper::info('// ' . MysqlResultInfo::DATA_TYPES[$int]);
    }

    public static function dumpIntMysqlFlags(int $int, DumperConfig $config): ?string
    {
        if ($int >= 1048576) {
            return null;
        }
        $flags = [];
        for ($n = 1; $n <= 524288; $n *= 2) {
            if (($int & $n) !== 0) {
                if (!isset(MysqlResultInfo::FLAGS[$n])) {
                    return null;
                }
                $flags[] = MysqlResultInfo::FLAGS[$n];
            }
        }
        $flags = implode('|', $flags);

        return Dumper::int((string) $int, $config) . ' ' . Dumper::info('// ' . $flags);
    }

    public static function dumpStringHidden(string $string, string $info, string $key, DumperConfig $config, int $depth): ?string
    {
        $key2 = ltrim($key, '$');
        if (!in_array($key, Dumper::$config->hiddenFields, true) && !in_array($key2, Dumper::$config->hiddenFields, true)) {
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

    public static function dumpStringPathList(string $string, string $info, string $key, DumperConfig $config, int $depth): ?string
    {
        if (!str_contains($string, PATH_SEPARATOR)) {
            return null;
        }

        return Dumper::string($string, $config, $depth, PATH_SEPARATOR) . ' ' . Dumper::info("// {$info}");
    }

    public static function dumpStringPath(string $string, string $info, string $key, DumperConfig $config, int $depth): ?string
    {
        if (preg_match('~file|path~', $key)
            || preg_match('~^[a-z]:[/\\\\]~i', $string)
            || ($string !== '' && $string[0] === '/')
            || str_contains($string, '/../')
        ) {
            $path = Dumper::normalizePath($string);
            $path = ($path !== rtrim($string, '/')) ? ', ' . $path : '';

            return Dumper::string($string, $config, $depth) . ' ' . Dumper::info("// {$info}{$path}");
        }

        return null;
    }

    public static function dumpStringKeyValuePair(string $string, string $info, string $key, DumperConfig $config, int $depth): ?string
    {
        if (!str_contains($string, '=')) {
            return null;
        }

        $string = Dumper::string($string, $config, $depth, PATH_SEPARATOR);
        $string = str_replace('=', Ansi::colorStart(Dumper::$colors['symbol']) . '=' . Ansi::colorStart(Dumper::$colors['string']), $string);

        return $string . ' ' . Dumper::info("// {$info}");
    }

    public static function dumpStringUuid(string $string, string $info, string $key, DumperConfig $config, int $depth): ?string
    {
        static $uuidRe = '~^(?:urn:uuid:)?{?([0-9a-f]{8})-?([0-9a-f]{4})-?([0-9a-f]{4})-?([0-9a-f]{4})-?([0-9a-f]{12})}?$~';

        if (strlen($string) === 16 && (Str::isBinary($string) || preg_match('~uuid~i', $key))) {
            $uuidInfo = Dumper::binaryUuidInfo($string, $config);
            if ($uuidInfo !== null) {
                return Dumper::stripInfo(Dumper::string($string, $config, $depth)) . ' ' . Dumper::info('// ' . $uuidInfo);
            }
        }

        if (preg_match($uuidRe, $string, $m)) {
            $uuidInfo = Dumper::uuidInfo(array_slice($m, 1), $config);
            if ($uuidInfo !== null) {
                return Dumper::string($string, $config, $depth) . ' ' . Dumper::info('// ' . $uuidInfo);
            }
        }

        return null;
    }

    public static function dumpStringColor(string $string, string $info, string $key, DumperConfig $config, int $depth): ?string
    {
        if (!Color::isColor($string, !preg_match('~color|background~i', $key))) {
            return null;
        }

        return Dumper::string($string, $config, $depth) . ' ' . Dumper::info("// " . Ansi::rgb('     ', null, $string));
    }

    public static function dumpStringCallable(string $string, string $info, string $key, DumperConfig $config, int $depth): ?string
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

            return Dumper::string($string, $config, $depth) . ' ' . Dumper::info("// {$info}");
        } else {
            foreach (get_loaded_extensions() as $extension) {
                if (in_array($string, get_extension_funcs($extension) ?: [], true)) {
                    $extension = strtolower($extension);
                    $info .= "callable from ext-{$extension}";

                    return Dumper::string($string, $config, $depth) . ' ' . Dumper::info("// {$info}");
                }
            }

            $info .= "callable";

            return Dumper::string($string, $config, $depth) . ' ' . Dumper::info("// {$info}");
        }
    }

    public static function dumpStringJson(string $string, string $info, string $key, DumperConfig $config, int $depth): ?string
    {
        if (Dumper::$config->jsonStrings === Dumper::JSON_KEEP_AS_IS) {
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
        if (Dumper::$config->jsonStrings === Dumper::JSON_DECODE) {
            return Dumper::exceptions('json:') . ' ' . Dumper::dump($data) . ' ' . Dumper::info("// {$info}");
        }

        if (strlen($string) < Dumper::$config->jsonPrettifyMinLength) {
            return null;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT);

        return Dumper::exceptions('prettified:') . ' ' . $json . ' ' . Dumper::info("// {$info}");
    }

}
