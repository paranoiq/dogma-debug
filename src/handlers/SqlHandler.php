<?php
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
use Doctrine\ORM\EntityManager;
use PDOStatement;
use function array_merge;
use function array_shift;
use function array_sum;
use function array_unique;
use function explode;
use function implode;
use function is_int;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
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
    public const OTHER = 512;

    public const NONE = 0;
    public const QUERY = self::SELECT | self::INSERT | self::DELETE | self::UPDATE;
    public const TRANSACTION = self::BEGIN | self::COMMIT | self::ROLLBACK;
    public const ALL = self::CONNECT | self::QUERY | self::TRANSACTION | self::OTHER;

    private const TYPES = [
        self::CONNECT => 'connect',
        self::SELECT => 'select',
        self::INSERT => 'insert',
        self::UPDATE => 'update',
        self::QUERY => 'query',
        self::BEGIN => 'begin',
        self::COMMIT => 'commit',
        self::ROLLBACK => 'rollback',
        self::OTHER => 'other',
    ];

    /** @var int - Types of events to show */
    public static $logEvents = self::ALL;

    /** @var bool - Show log even if it is OFF when error happens */
    public static $errorForcesLog = true;

    /** @var bool */
    public static $filterTrace = true;

    /** @var string[] */
    public static $traceFilters = [];

    /** @var int */
    public static $traceLength = 1;

    /** @var array<int|string, callable> - Query filters for modifying logged SQL, optionally indexed by connection name */
    public static $queryFilters = [
        [self::class, 'normalizeWhitespace'],
        'doctrine' => [self::class, 'filterDoctrineSelects'],
        [self::class, 'simpleHighlighting']
    ];

    /** @var array<int, int> */
    private static $events = [];

    /** @var array<int, float> */
    private static $time = [];

    /** @var array<int, int> */
    private static $rows = [];

    /** @var array<int, int> */
    private static $errors = [];

    /**
     * @param int|string|null $errorCode
     */
    public static function logUnknown(
        string $query,
        ?int $rows,
        float $duration,
        ?string $connection = null,
        ?string $errorMessage = null,
        $errorCode = null
    ): void
    {
        self::log(self::getType($query), $query, $rows, $duration, $connection, $errorMessage, $errorCode);
    }

    /**
     * @param int|string|null $errorCode
     */
    public static function log(
        int $type,
        ?string $query,
        ?int $rows,
        float $duration,
        ?string $connection = null,
        ?string $errorMessage = null,
        $errorCode = null
    ): void
    {
        if (!isset(self::$events[$type])) {
            self::$events[$type] = 0;
            self::$time[$type] = 0.0;
            self::$rows[$type] = 0;
            self::$errors[$type] = 0;
        }
        self::$events[$type]++;
        self::$time[$type] += $duration;
        if ($rows !== null) {
            self::$rows[$type] += $rows;
        }
        $isError = $errorCode !== null || $errorMessage !== null;
        if ($isError) {
            self::$errors[$type]++;
        }

        if (!($type & self::$logEvents) && (!$isError || !self::$errorForcesLog)) {
            return;
        }

        if ($query) {
            foreach (self::$queryFilters as $forConnection => $filter) {
                if ($forConnection === $connection || is_int($forConnection)) {
                    $query = $filter($query);
                }
            }
            $message = Ansi::lyellow($query . ';');
        } else {
            $message = Ansi::lyellow(strtoupper(self::TYPES[$type]) . ';');
        }

        if ($rows !== null) {
            $result = $isError ? ', FAILED' : ', OK';
            $info = Ansi::color(' -- ' . Units::units($rows, 'row') . $result, Dumper::$colors['info']);
        } else {
            $result = $isError ? 'FAILED' : 'OK';
            $info = Ansi::color(' -- ' . $result, Dumper::$colors['info']);
        }

        $message = Ansi::white($connection ? " DB {$connection}: " : ' DB: ', Debugger::$handlerColors[self::NAME])
            . ' ' . $message . $info;

        if ($isError) {
            $errorMessage = str_replace('; check the manual that corresponds to your MySQL server version for the right syntax to use', '', (string) $errorMessage);
            $message .= "\n " . Ansi::color($errorCode . ' ' . $errorMessage, Dumper::$colors['errors']);
        }

        $callstack = Callstack::get(array_merge(Dumper::$traceFilters, self::$traceFilters), self::$filterTrace);
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
                'connect' => self::$events[self::CONNECT] ?? 0,
                'select' => self::$events[self::SELECT] ?? 0,
                'insert' => self::$events[self::INSERT] ?? 0,
                'delete' => self::$events[self::DELETE] ?? 0,
                'update' => self::$events[self::UPDATE] ?? 0,
                'query' => self::$events[self::QUERY] ?? 0,
                'begin' => self::$events[self::BEGIN] ?? 0,
                'commit' => self::$events[self::COMMIT] ?? 0,
                'rollback' => self::$events[self::ROLLBACK] ?? 0,
                'other' => self::$events[self::OTHER] ?? 0,
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
                'other' => self::$time[self::OTHER] ?? 0.0,
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
                'other' => self::$rows[self::OTHER] ?? 0,
            ],
            'errors' => [
                'connect' => self::$errors[self::CONNECT] ?? 0,
                'select' => self::$errors[self::SELECT] ?? 0,
                'insert' => self::$errors[self::INSERT] ?? 0,
                'delete' => self::$errors[self::DELETE] ?? 0,
                'update' => self::$errors[self::UPDATE] ?? 0,
                'query' => self::$errors[self::QUERY] ?? 0,
                'begin' => self::$errors[self::BEGIN] ?? 0,
                'commit' => self::$errors[self::COMMIT] ?? 0,
                'rollback' => self::$errors[self::ROLLBACK] ?? 0,
                'other' => self::$errors[self::OTHER] ?? 0,
            ],
        ];

        $stats['events']['total'] = array_sum($stats['events']);
        $stats['time']['total'] = array_sum($stats['time']);
        $stats['rows']['total'] = array_sum($stats['rows']);
        $stats['errors']['total'] = array_sum($stats['errors']);

        return $stats;
    }

    // DBAL handlers ---------------------------------------------------------------------------------------------------

    public static function enableDoctrineLogger(EntityManager $entityManager, ?string $connection = 'doctrine'): void
    {
        require_once __DIR__ . '/DoctrineSqlLogger.php';

        $logger = new DoctrineSqlLogger($connection);

        $entityManager->getConnection()->getConfiguration()->setSQLLogger($logger);
    }

    public static function disableDoctrineLogger(EntityManager $entityManager): void
    {
        $entityManager->getConnection()->getConfiguration()->setSQLLogger();
    }

    public static function handleDibiEvent(Event $event, ?string $connection = null): void
    {
        $errorMessage = $errorCode = null;
        if ($event->result instanceof DriverException) {
            $errorMessage = $event->result->getMessage();
            $errorCode = $event->result->getCode();
        }

        self::log($event->type ?: self::OTHER, $event->sql, $event->count, $event->time, $connection, $errorMessage, $errorCode);
    }

    /**
     * @param array<int|string, mixed> $params
     * @param int|string|null $errorCode
     */
    public static function logPdoStatementExecute(
        PDOStatement $statement,
        array $params,
        ?int $rows,
        float $duration,
        ?string $connection = null,
        ?string $errorMessage = null,
        $errorCode = null
    ): void
    {
        $query = $statement->queryString;
        // todo: order and names
        $query = preg_replace_callback('~[?]~', static function () use (&$params): string {
            $value = array_shift($params);
            if (is_string($value)) {
                $oldEscaping = Dumper::$stringsEscaping;
                Dumper::$stringsEscaping = Dumper::ESCAPING_MYSQL;
                $value = Dumper::string($value);
                Dumper::$stringsEscaping = $oldEscaping;
            } else {
                $value = Dumper::dumpValue($value, 0);
            }
            return $value . Ansi::colorStart(Dumper::$colors['value']);
        }, $query);
        self::log(self::getType($query), $query, $rows, $duration, $connection, $errorMessage, $errorCode);
    }

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

    // helpers ---------------------------------------------------------------------------------------------------------

    public static function getType(string $sql): int
    {
        if (preg_match('~^(SELECT|\\()~i', $sql) !== 0) {
            return self::SELECT;
        } elseif (preg_match('~^(INSERT|REPLACE)~i', $sql) !== 0) {
            return self::INSERT;
        } elseif (preg_match('~^UPDATE~i', $sql) !== 0) {
            return self::UPDATE;
        } elseif (preg_match('~^DELETE~i', $sql) !== 0) {
            return self::DELETE;
        } elseif (preg_match('~^(BEGIN|START TRANSACTION|SAVEPOINT)~i', $sql) !== 0) {
            return self::BEGIN;
        } elseif (preg_match('~^(COMMIT|RELEASE SAVEPOINT)~i', $sql) !== 0) {
            return self::COMMIT;
        } elseif (preg_match('~^ROLLBACK~i', $sql) !== 0) {
            return self::ROLLBACK;
        } else {
            return self::OTHER;
        }
    }

    public static function normalizeWhitespace(string $query): string
    {
        $query = preg_replace('~\n\s*\n~m', "\n", $query); // remove empty lines
        $query = preg_replace('~\n\s{2,1000}~', "\n  ", $query); // normalize indenting
        $query = preg_replace('~\n  (?=AND |OR |JOIN |LEFT JOIN )~i', "\n    ", $query); // indent conditions and joins
        $query = preg_replace('~\s+;\s*$~m', ";", $query); // remove whitespace around ";"

        return trim($query);
    }

    public static function simpleHighlighting(string $query): string
    {
        $commentColor = Ansi::colorStart(Dumper::$colors['info']);
        $keywordColor = Ansi::colorStart(Dumper::$colors['value2']);
        $textColor = Ansi::colorStart(Dumper::$colors['value']);
        $reserved = implode('|', Sql::getKeywords());

        $query = preg_replace("~(?<=\s|\(|^)({$reserved})(?=\s|\)|;|$)~i", "{$keywordColor}\\1{$textColor}", $query); // highlight keywords
        $query = preg_replace("~\n  (?=[^\\e| ])~", "\n    ", $query); // indent non-keywords
        $query = preg_replace_callback("~-- [^\n]+\n~", static function (array $match) use ($commentColor, $textColor): string {
            return $commentColor . Ansi::removeColors($match[0]) . $textColor;
        }, $query);
        $query = preg_replace_callback("~/\\*[^*]+\\*/~", static function (array $match) use ($commentColor, $textColor): string {
            return $commentColor . Ansi::removeColors($match[0]) . $textColor;
        }, $query);

        return $query;
    }

    public static function filterDoctrineSelects(string $query): string
    {
        static $column = '[a-z\d_]+\.[a-z\d_]+(?: AS [a-z\d_]+)?';

        if (preg_match("~SELECT ({$column}(?:, {$column})*) FROM~i", $query, $m) === 0) {
            return $query;
        }

        $tables = [];
        foreach (explode(', ', $m[1]) as $item) {
            $table = explode('.', $item)[0];
            $tables[] = $table . '.?';
        }
        $tables = array_unique($tables);
        $tables = implode(', ', $tables);

        return preg_replace("~SELECT {$column}(?:, {$column})* FROM~i", "SELECT {$tables} FROM", $query);
    }

}
