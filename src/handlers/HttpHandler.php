<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function array_merge;
use function array_search;
use function array_sum;
use function strtolower;
use function strtoupper;

/**
 * Tracks and displays outgoing HTTP(S) requests
 */
class HttpHandler
{

    public const NAME = 'http';

    public const GET = 1;
    public const HEAD = 2;
    public const POST = 4;
    public const PUT = 8;
    public const DELETE = 16;
    public const CONNECT = 32;
    public const OPTIONS = 64;
    public const TRACE = 128;
    public const PATCH = 256;

    public const NONE = 0;
    public const ALL = self::GET | self::HEAD | self::POST | self::PUT | self::DELETE | self::CONNECT | self::OPTIONS | self::TRACE | self::PATCH;

    private const METHODS = [
        self::GET => 'get',
        self::HEAD => 'head',
        self::POST => 'post',
        self::PUT => 'put',
        self::DELETE => 'delete',
        self::CONNECT => 'connect',
        self::OPTIONS => 'options',
        self::TRACE => 'trace',
        self::PATCH => 'patch',
    ];

    /** @var int - Types of events to show */
    public static $logEvents = self::ALL;

    /** @var bool */
    public static $filterTrace = true;

    /** @var string[] */
    public static $traceFilters = [];

    /** @var int */
    public static $traceLength = 1;

    /** @var int[] */
    private static $events = [];

    /** @var float[] */
    private static $time = [];

    /** @var int[] */
    private static $data = [];

    public static function log(
        string $method,
        ?string $url,
        ?int $dataSize,
        float $duration,
        ?int $responseCode = null,
        ?int $errorCode = null
    ): void
    {
        $method = strtolower($method);
        if (!isset(self::$events[$method])) {
            self::$events[$method] = 0;
            self::$time[$method] = 0.0;
            self::$data[$method] = 0;
        }
        self::$events[$method]++;
        self::$time[$method] += $duration;
        if ($dataSize !== null) {
            self::$data[$method] += $dataSize;
        }

        $methodId = array_search($method, self::METHODS);
        if (!($methodId & self::$logEvents)) {
            return;
        }

        if ($url) {
            $message = Ansi::lyellow(strtoupper($method) . ' ' . $url);
        } else {
            $message = Ansi::lyellow(strtoupper($method) . ' ???');
        }

        $countFormatted = $dataSize !== null
            ? Ansi::color(' -- ' . Units::units($dataSize, 'row'), Dumper::$colors['info'])
            : '';

        $message = Ansi::white(' http: ', Debugger::$handlerColors[self::NAME]) . ' ' . $message . $countFormatted;

        $callstack = Callstack::get(array_merge(Dumper::$config->traceFilters, self::$traceFilters), self::$filterTrace);
        $backtrace = Dumper::formatCallstack($callstack, self::$traceLength, 0, 0);

        Debugger::send(Message::SQL, $message, $backtrace, $duration);
    }

    /**
     * @return array<int[]|float[]>
     */
    public static function getStats(): array
    {
        $stats = [
            'events' => [
                'get' => self::$events['get'] ?? 0,
                'head' => self::$events['head'] ?? 0,
                'post' => self::$events['post'] ?? 0,
                'put' => self::$events['put'] ?? 0,
                'delete' => self::$events['delete'] ?? 0,
                'connect' => self::$events['connect'] ?? 0,
                'options' => self::$events['options'] ?? 0,
                'trace' => self::$events['trace'] ?? 0,
                'patch' => self::$events['patch'] ?? 0,
            ],
            'time' => [
                'get' => self::$time['get'] ?? 0.0,
                'head' => self::$time['head'] ?? 0.0,
                'post' => self::$time['post'] ?? 0.0,
                'put' => self::$time['put'] ?? 0.0,
                'delete' => self::$time['delete'] ?? 0.0,
                'connect' => self::$time['connect'] ?? 0.0,
                'options' => self::$time['options'] ?? 0.0,
                'trace' => self::$time['trace'] ?? 0.0,
                'patch' => self::$time['patch'] ?? 0.0,
            ],
            'data' => [
                'get' => self::$data['get'] ?? 0,
                'head' => self::$data['head'] ?? 0,
                'post' => self::$data['post'] ?? 0,
                'put' => self::$data['put'] ?? 0,
                'delete' => self::$data['delete'] ?? 0,
                'connect' => self::$data['connect'] ?? 0,
                'options' => self::$data['options'] ?? 0,
                'trace' => self::$data['trace'] ?? 0,
                'patch' => self::$data['patch'] ?? 0,
            ],
        ];

        $stats['events']['total'] = array_sum($stats['events']);
        $stats['time']['total'] = array_sum($stats['time']);
        $stats['data']['total'] = array_sum($stats['data']);

        return $stats;
    }

    // handlers --------------------------------------------------------------------------------------------------------

    public static function useDoctrineTraceFilters(): void
    {
        self::$traceFilters = array_merge(self::$traceFilters, [
            '~^Doctrine\\\\DBAL\\\\Connection~',
            '~^Doctrine\\\\DBAL\\\\Driver~',
            '~^Doctrine\\\\ORM\\\\UnitOfWork~',
            '~^Doctrine\\\\ORM\\\\AbstractQuery~',
            '~^Doctrine\\\\ORM\\\\Query~',
            '~^Doctrine\\\\ORM\\\\Query\\\\Exec\\\\SingleSelectExecutor~',
            '~^Doctrine\\\\ORM\\\\Internal\\\\Hydration\\\\ObjectHydrator~',
            '~^Doctrine\\\\ORM\\\\Internal\\\\Hydration\\\\AbstractHydrator~',
            '~^Doctrine\\\\ORM\\\\Persisters\\\\Entity\\\\BasicEntityPersister~',
        ]);
    }

}
