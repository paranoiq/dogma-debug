<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use Dibi\DriverException;
use Dibi\Event;
use function array_sum;
use function preg_replace;
use function strtoupper;

/**
 * Tracks and displays SQL operations on registered drivers
 */
class SqlHandler
{

    public const NAME = 'sql';

    public const CONNECT = 1;
    public const SELECT = 4;
    public const INSERT = 8;
    public const DELETE = 16;
    public const UPDATE = 32;
    public const BEGIN = 64;
    public const COMMIT = 128;
    public const ROLLBACK = 256;

    public const NONE = 0;
    public const QUERY = self::SELECT | self::INSERT | self::DELETE | self::UPDATE;
    public const TRANSACTION = self::BEGIN | self::COMMIT | self::ROLLBACK;
    public const ALL = self::CONNECT | self::QUERY | self::TRANSACTION;

    private const TYPES = [
        self::CONNECT => 'connect',
        self::SELECT => 'select',
        self::INSERT => 'insert',
        self::UPDATE => 'update',
        self::QUERY => 'query',
        self::BEGIN => 'begin',
        self::COMMIT => 'commit',
        self::ROLLBACK => 'rollback',
    ];

    /** @var int Types of events to log */
    public static $logEvents = self::ALL;

    /** @var bool */
    public static $filterTrace = true;

    /** @var int[] */
    private static $events = [];

    /** @var float[] */
    private static $time = [];

    /** @var int[] */
    private static $rows = [];

    public static function log(
        int $type,
        ?string $query,
        ?int $rows,
        float $duration,
        ?string $connection = null,
        ?string $errorMessage = null,
        ?int $errorCode = null
    ): void
    {
        if (!isset(self::$events[$type])) {
            self::$events[$type] = 0;
            self::$time[$type] = 0.0;
            self::$rows[$type] = 0;
        }
        self::$events[$type]++;
        self::$time[$type] += $duration;
        if ($rows !== null) {
            self::$rows[$type] += $rows;
        }

        if (!($type & self::$logEvents)) {
            return;
        }

        if ($query) {
            $query = preg_replace('~\n\s*\n~m', "\n", $query);
            $message = preg_replace('~\n\s{2,1000}~', "\n  ", $query);
        } else {
            $message = strtoupper(self::TYPES[$type]);
        }
        $message .= ';';

        $countFormatted = $rows !== null
            ? ' ' . Ansi::color($rows . ($rows === 1 ? ' row' : ' rows'), Dumper::$colors['value'])
            : '';

        $message = Ansi::white($connection ? " DB $connection: " : ' DB: ', Debugger::$handlerColors[self::NAME])
            . ' ' . $message . $countFormatted;

        $callstack = Callstack::get(Dumper::$traceFilters, self::$filterTrace);
        $backtrace = Dumper::formatCallstack($callstack, 10, 0, 0);

        Debugger::send(Packet::SQL, $message, $backtrace, $duration);
    }

    public static function handleDibiEvent(Event $event, ?string $connection = null): void
    {
        $errorMessage = $errorCode = null;
        if ($event->result instanceof DriverException) {
            $errorMessage = $event->result->getMessage();
            $errorCode = $event->result->getCode();
        }

        self::log($event->type, $event->sql, $event->count, $event->time, $connection, $errorMessage, $errorCode);
    }

    /**
     * @return array<int[]|float[]>
     */
    public static function getStats(): array
    {
        $stats = [
            'events' => [
                'connect' => self::$events[self::CONNECT] ?? 0,
                'select' => self::$events[self::SELECT] ?? 0,
                'insert' => self::$events[self::INSERT] ?? 0,
                'delete' => self::$events[self::DELETE] ?? 0,
                'update' => self::$events[self::UPDATE] ?? 0,
                'query' => self::$events[self::QUERY] ?? 0,
                'begin' => self::$events[self::BEGIN] ?? 0,
                'commit' => self::$events[self::COMMIT] ?? 0,
                'rollback' => self::$events[self::ROLLBACK] ?? 0,
            ],
            'time' => [
                'connect' => self::$time[self::CONNECT] ?? 0.0,
                'select' => self::$time[self::SELECT] ?? 0.0,
                'insert' => self::$time[self::INSERT] ?? 0.0,
                'delete' => self::$time[self::DELETE] ?? 0.0,
                'update' => self::$time[self::UPDATE] ?? 0.0,
                'query' => self::$time[self::QUERY] ?? 0.0,
                'begin' => self::$time[self::BEGIN] ?? 0.0,
                'commit' => self::$time[self::COMMIT] ?? 0.0,
                'rollback' => self::$time[self::ROLLBACK] ?? 0.0,
            ],
            'rows' => [
                'connect' => self::$rows[self::CONNECT] ?? 0,
                'select' => self::$rows[self::SELECT] ?? 0,
                'insert' => self::$rows[self::INSERT] ?? 0,
                'delete' => self::$rows[self::DELETE] ?? 0,
                'update' => self::$rows[self::UPDATE] ?? 0,
                'query' => self::$rows[self::QUERY] ?? 0,
                'begin' => self::$rows[self::BEGIN] ?? 0,
                'commit' => self::$rows[self::COMMIT] ?? 0,
                'rollback' => self::$rows[self::ROLLBACK] ?? 0,
            ],
        ];

        $stats['events']['total'] = array_sum($stats['events']);
        $stats['time']['total'] = array_sum($stats['time']);
        $stats['rows']['total'] = array_sum($stats['rows']);

        return $stats;
    }

}
