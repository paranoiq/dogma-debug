<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function array_map;
use function array_merge;
use function array_shift;
use function array_sum;
use function bzdecompress;
use function count;
use function ctype_digit;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function preg_match;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;
use function unserialize;
use const COUNT_RECURSIVE;

/**
 * Tracks and displays communication with AMQP (RabbitMQ etc.)
 *
 * @see https://en.wikipedia.org/wiki/Advanced_Message_Queuing_Protocol
 */
class AmqpHandler
{

    public const NAME = 'amqp';

    /** @var bool Turn logging on/off */
    public static $log = true;

    /** @var string[] Commands white list */
    //public static $logEvents = [];

    /** @var string[] Commands black list */
    //public static $logFilter = [];

    /** @var int Max length of logged message [bytes after formatting] */
    public static $maxLength = 2000;

    /** @var bool */
    public static $filterTrace = true;

    /** @var string[] */
    public static $traceFilters = [];

    // internals -------------------------------------------------------------------------------------------------------

    /** @var bool */
    private static $enabled = false;

    /** @var string */
    private static $lastCommand;

    /** @var string[] */
    private static $keys = [];

    /** @var int[] */
    private static $events = [];

    /** @var int[] */
    private static $data = [];

    /** @var int[] */
    private static $rows = [];

    /** @var float[] */
    private static $time = [];

    /** @var string */
    private static $readBuffer = '';

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    /**
     * @param 'tcp'|'udp'|'unix' $protocol
     */
    public static function enableForAmqp(string $protocol = 'tcp', int $port = 5672): void
    {
        if (!StreamInterceptor::enabled()) {
            StreamInterceptor::interceptFileFunctions(Intercept::SILENT);
            $activated = StreamInterceptor::class;
            $by = __CLASS__ . '::' . __METHOD__;
            Debugger::dependencyInfo("{$activated} activated by {$by}() to track filesystem functions.", true);
        }

        $re = "~:{$port}$~";
        StreamInterceptor::$redirect[$protocol]['fwrite'][$re] = [self::class, 'amqpFwrite'];
        StreamInterceptor::$redirect[$protocol]['fread'][$re] = [self::class, 'amqpFread'];
        StreamInterceptor::$redirect[$protocol]['fgets'][$re] = [self::class, 'amqpFgets'];

        self::$traceFilters = [
            '~^Predis\\\\Connection\\\\StreamConnection~',
            '~^Predis\\\\Connection\\\\AbstractConnection~',
            '~^Predis\\\\Client~',
            '~^Predis\\\\Session\\\\Handler~',
        ];

        self::$enabled = true;
    }

    // php-amqp --------------------------------------------------------------------------------------------------------

    /**
     * @param mixed[] $params
     * @param mixed $return
     */
    public static function amqpFwrite(string $path, float $duration, array $params, $return): void
    {
        $query = $params[0];

        if (is_array($query)) {
            $command = array_shift($query);
            $args = array_map([self::class, 'formatArgument'], $query);
            $query = Ansi::lgreen($command) . ' ' . implode(' ', $args);

            self::logCommand($command, $args, $duration, strlen($params[0]));
        } else {
            $query = Ansi::lgreen($query);

            self::logCommand($query, [], $duration, strlen($params[0]));
        }

        if (!self::$log) {
            return;
        }

        if (strlen($query) > self::$maxLength) {
            $query = substr($query, 0, self::$maxLength) . ' ' . Dumper::exceptions('...');
        }

        $message = Ansi::white(' rabbit: ', Ansi::DGREEN) . ' ' . $query;

        $callstack = Callstack::get(array_merge(Dumper::$config->traceFilters, self::$traceFilters), self::$filterTrace);
        $trace = Dumper::formatCallstack($callstack, 1, 0, 0);

        Debugger::send(Message::AMQP, $message, $trace, $duration);
    }

    /**
     * @param mixed[] $params
     * @param mixed $return
     */
    public static function amqpFread(string $path, float $duration, array $params, $return): void
    {
        $key = null;
        if ($return === false) {
            $response = Dumper::exceptions('ERROR');
        } else {
            if ($return !== '' && !str_ends_with($return, "\r\n")) {
                self::$readBuffer .= $return;
                return;
            } elseif (self::$readBuffer !== '') {
                $return = self::$readBuffer . $return;
                self::$readBuffer = '';
            }

            if (self::$keys !== []) {
                $key = array_shift(self::$keys);
            }

            [$response, $rows] = self::formatResponse($return, $key);

            // stats
            self::$time[self::$lastCommand] += $duration;
            self::$data[self::$lastCommand] += strlen($return);
            self::$rows[self::$lastCommand] += $rows;
        }

        if (!self::$log) {
            return;
        }

        if (strlen($response) > self::$maxLength) {
            $response = substr($response, 0, self::$maxLength) . ' ' . Dumper::exceptions('...');
        }

        if ($key !== null) {
            $message = Ansi::white(' ' . self::NAME . ': ', Ansi::DGREEN)
                . ' ' . Dumper::key($key, true) . Dumper::symbol(':') . ' ' . $response;
        } else {
            $message = Ansi::white(' ' . self::NAME . ': ', Ansi::DGREEN) . ' ' . $response;
        }

        $callstack = Callstack::get(array_merge(Dumper::$config->traceFilters, self::$traceFilters), self::$filterTrace);
        $trace = Dumper::formatCallstack($callstack, 1, 0, 0);

        Debugger::send(Message::AMQP, $message, $trace, $duration);
    }

    /**
     * @param mixed[] $params
     * @param mixed $return
     */
    public static function amqpFgets(string $path, float $duration, array $params, $return): void
    {
        if (is_string($return) && ($return[0] === '*' || $return[0] === '$') && $return !== "$-1\r\n") {
            return;
        }
        self::amqpFread($path, $duration, $params, $return);
    }

    private static function formatArgument(string $message): string
    {
        $prefix = '';
        if (str_starts_with($message, 'BZ')) {
            $res = bzdecompress($message);
            if (is_string($res)) {
                $message = $res;
                $prefix = Dumper::exceptions('zip:') . ' ';
            }
        }

        if ($message[0] === '{') {
            // json encoded
            $prefix .= Dumper::exceptions('json:') . ' ';
            $value = Dumper::dumpValue(json_decode($message, true), 0);
        } elseif (preg_match('~^a:[0-9]+:\\{~', $message)) {
            // php serializes
            $prefix .= Dumper::exceptions('serialized:') . ' ';
            $value = Dumper::dumpValue(unserialize($message, ['allowed_classes' => true]), 0);
        } else {
            $value = Dumper::string($message, 0);
        }

        return $prefix . $value;
    }

    /**
     * @return array{string, int}
     */
    private static function formatResponse(string $message, string $key): array
    {
        $rows = 1;
        $prefix = '';
        if (str_starts_with($message, 'BZ')) {
            $res = bzdecompress($message);
            if (is_string($res)) {
                $message = $res;
                $prefix = Dumper::exceptions('zip:') . ' ';
            }
        }

        $c = $message[0];
        if ($message === "$-1\r\n") {
            $rows = 0;
            $value = Dumper::null('null');
        } elseif ($c === '+' || $c === '-' || $c === ':' || $c === '$' || $c === '*') {
            // resp formatted responses
            if ($c === '-') {
                $prefix .= Ansi::lred('error:') . ' ';
            }
            $data = $message;
            $rows = is_array($data) ? count($data, COUNT_RECURSIVE) : 1; // @phpstan-ignore-line todo copied from RedisHandler
            $value = Dumper::dumpValue($data, 0, $key);
        } elseif ($message[0] === '{') {
            // json encoded
            $prefix .= Dumper::exceptions('json:') . ' ';
            $value = Dumper::dumpValue(json_decode($message, true), 0, $key);
        } elseif (preg_match('~^a:[0-9]+:\\{~', $message)) {
            // php serializes
            $prefix .= Dumper::exceptions('serialized:') . ' ';
            $value = Dumper::dumpValue(unserialize($message, ['allowed_classes' => true]), 0, $key);
        } else {
            $t = trim($message);
            if (ctype_digit($t)) {
                $value = Dumper::dumpInt((int) $t, $key);
            } elseif (is_numeric($t)) {
                $value = Dumper::dumpFloat((float) $t, $key);
            } elseif ($t !== '') {
                $value = Dumper::dumpString($t, 0, $key);
            } else {
                $value = Dumper::null('null');
            }
        }

        return [$prefix . $value, $rows];
    }

    // stats -----------------------------------------------------------------------------------------------------------

    /**
     * @param string[] $args
     */
    private static function logCommand(string $command, array $args, float $duration, int $data): void
    {
        // stats
        self::$lastCommand = $command;
        if (!isset(self::$events[$command])) {
            self::$events[$command] = 1;
            self::$time[$command] = $duration;
            self::$data[$command] = $data;
            self::$rows[$command] = 0;
        } else {
            self::$events[$command]++;
            self::$time[$command] += $duration;
            self::$data[$command] += $data;
        }

        // keys for results
        if ($command === 'MGET') {
            self::$keys = $args;
        } elseif ($command === 'GET') {
            self::$keys = [$args[1]];
        } else {
            self::$keys = [];
        }
    }

    /**
     * @return array{events: int[], data: int[], rows: int[], time: float[]}
     */
    public static function getStats(): array
    {
        $stats = [
            'events' => self::$events,
            'data' => self::$data,
            'rows' => self::$rows,
            'time' => self::$time,
        ];

        $stats['events']['total'] = array_sum($stats['events']);
        $stats['data']['total'] = array_sum($stats['data']);
        $stats['rows']['total'] = array_sum($stats['rows']);
        $stats['time']['total'] = array_sum($stats['time']);

        return $stats;
    }

}
