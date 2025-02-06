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
use SqlFtw\Formatter\Formatter;
use SqlFtw\Parser\Parser;
use SqlFtw\Parser\ParserConfig;
use SqlFtw\Platform\Platform;
use SqlFtw\Session\Session;
use SqlFtw\Sql\Assignment;
use SqlFtw\Sql\Dml\Insert\InsertSetCommand;
use SqlFtw\Sql\Dml\Insert\InsertValuesCommand;
use SqlFtw\Sql\Expression\Operator;
use Throwable;
use function array_keys;
use function array_merge;
use function array_shift;
use function array_sum;
use function class_exists;
use function count;
use function explode;
use function implode;
use function is_int;
use function is_string;
use function iterator_to_array;
use function max;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function str_replace;
use function str_starts_with;
use function strtoupper;
use function trim;

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
        [self::class, 'convertInsertToInsertSet'], // skipped when SQLFTW is not available
        [self::class, 'stripComments'],
        [self::class, 'stripDoctrineSelectColumns'],
        //[self::class, 'stripInsertColumnList'],
        [self::class, 'normalizeWhitespace'],
        [self::class, 'simpleHighlighting'],
    ];

    /** @var bool - Show query execution time */
    public static $showQueryTime = true;

    /** @var string - Default server name and version for query parsing */
    public static $defaultServerInfo = 'mysql 8.0';

    /** @var array<int, int> */
    private static $events = [];

    /** @var array<int, float> */
    private static $time = [];

    /** @var array<int, float> */
    private static $maxTime = [];

    /** @var array<int, int> */
    private static $rows = [];

    /** @var array<int, int> */
    private static $errors = [];

    /**
     * @param int|string|null $lastInsertId
     * @param int|string|null $errorCode
     */
    public static function logUnknown(
        string $query,
        float $duration,
        ?int $rows = 0,
        $lastInsertId = null,
        ?string $connection = null,
        ?string $serverInfo = null,
        ?string $errorMessage = null,
        $errorCode = null
    ): void
    {
        $type = self::getType($query);
        self::log($type, $query, $duration, $rows, $lastInsertId, $connection, $serverInfo, $errorMessage, $errorCode);
    }

    /**
     * @param int|string|null $lastInsertId
     * @param int|string|null $errorCode
     */
    public static function log(
        int $type,
        ?string $query,
        float $duration,
        ?int $rows = null,
        $lastInsertId = null,
        ?string $connection = null,
        ?string $serverInfo = null, // e.g. "mysql 8.0.32"
        ?string $errorMessage = null,
        $errorCode = null
    ): void
    {
        if (!isset(self::$events[$type])) {
            self::$events[$type] = 0;
            self::$time[$type] = 0.0;
            self::$maxTime[$type] = 0.0;
            self::$rows[$type] = 0;
            self::$errors[$type] = 0;
        }
        self::$events[$type]++;
        self::$time[$type] += $duration;
        self::$maxTime[$type] = max(self::$maxTime[$type], $duration);
        if ($rows !== null) {
            self::$rows[$type] += $rows;
        }
        $isError = ($errorCode !== null && $errorCode !== 0) || ($errorMessage !== null && $errorMessage !== '');
        if ($isError) {
            self::$errors[$type]++;
        }

        if (!($type & self::$logEvents) && (!$isError || !self::$errorForcesLog)) {
            return;
        }

        if ($query) {
            foreach (self::$queryFilters as $forConnection => $filter) {
                if ($forConnection === $connection || is_int($forConnection)) {
                    $query = $filter($query, $serverInfo);
                }
            }
            $message = Ansi::lyellow($query . ';');
        } else {
            $message = Ansi::lyellow(strtoupper(self::TYPES[$type]) . ';');
        }

        $parts = [];
        if ($rows !== null) {
            $parts[] = Units::units($rows, 'row');
        }
        if ($lastInsertId !== null && $lastInsertId !== 0) {
            $parts[] = 'last id: ' . $lastInsertId;
        }
        $parts[] = $isError ? 'FAILED' : 'OK';
        $info = Ansi::color(' -- ' . implode(', ', $parts), Dumper::$colors['info']);

        $message = Ansi::white($connection ? " {$connection}: " : ' DB: ', Debugger::$handlerColors[self::NAME])
            . ' ' . $message . $info;

        if ($isError) {
            $errorMessage = str_replace('; check the manual that corresponds to your MySQL server version for the right syntax to use', '', (string) $errorMessage);
            $message .= "\n" . Ansi::white(' ' . $errorCode . ' ' . $errorMessage . ' ', Dumper::$colors['errors']);
        }

        $callstack = Callstack::get(array_merge(Dumper::$traceFilters, self::$traceFilters), self::$filterTrace);
        $backtrace = Dumper::formatCallstack($callstack, self::$traceLength, 0, 0);

        Debugger::send(Message::SQL, $message, $backtrace, self::$showQueryTime ? $duration : null);
    }

    public static function getEvents(): array
    {
        return self::$events;
    }

    public static function resetStats(): void
    {
        self::$events = [];
        self::$time = [];
        self::$maxTime = [];
        self::$rows = [];
        self::$errors = [];
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
            'avg_time' => [
                'connect' => (self::$time[self::CONNECT] ?? 0.0) / (self::$events[self::CONNECT] ?? 1),
                'select' => (self::$time[self::SELECT] ?? 0.0) / (self::$events[self::SELECT] ?? 1),
                'insert' => (self::$time[self::INSERT] ?? 0.0) / (self::$events[self::INSERT] ?? 1),
                'delete' => (self::$time[self::DELETE] ?? 0.0) / (self::$events[self::DELETE] ?? 1),
                'update' => (self::$time[self::UPDATE] ?? 0.0) / (self::$events[self::UPDATE] ?? 1),
                'query' => (self::$time[self::QUERY] ?? 0.0) / (self::$events[self::QUERY] ?? 1),
                'begin' => (self::$time[self::BEGIN] ?? 0.0) / (self::$events[self::BEGIN] ?? 1),
                'commit' => (self::$time[self::COMMIT] ?? 0.0) / (self::$events[self::COMMIT] ?? 1),
                'rollback' => (self::$time[self::ROLLBACK] ?? 0.0) / (self::$events[self::ROLLBACK] ?? 1),
                'other' => (self::$time[self::OTHER] ?? 0.0) / (self::$events[self::OTHER] ?? 1),
            ],
            'max_time' => [
                'connect' => self::$maxTime[self::CONNECT] ?? 0.0,
                'select' => self::$maxTime[self::SELECT] ?? 0.0,
                'insert' => self::$maxTime[self::INSERT] ?? 0.0,
                'delete' => self::$maxTime[self::DELETE] ?? 0.0,
                'update' => self::$maxTime[self::UPDATE] ?? 0.0,
                'query' => self::$maxTime[self::QUERY] ?? 0.0,
                'begin' => self::$maxTime[self::BEGIN] ?? 0.0,
                'commit' => self::$maxTime[self::COMMIT] ?? 0.0,
                'rollback' => self::$maxTime[self::ROLLBACK] ?? 0.0,
                'other' => self::$maxTime[self::OTHER] ?? 0.0,
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

        self::log($event->type ?: self::OTHER, $event->sql, $event->time, $event->count, $connection, $errorMessage, $errorCode);
    }

    /**
     * @param array<int|string, mixed> $params
     * @param int|string|null $errorCode
     */
    public static function logPdoStatementExecute(
        PDOStatement $statement,
        array $params,
        float $duration,
        ?int $rows,
        ?int $lastInsertId,
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
        self::log(self::getType($query), $query, $duration, $rows, $lastInsertId, $connection, $errorMessage, $errorCode);
    }

    public static function useDoctrineTraceFilters(): void
    {
        self::$traceFilters = array_merge(self::$traceFilters, [
            '~^Doctrine\\\\Common\\\\Collections~',
            '~^Doctrine\\\\DBAL\\\\Connection~',
            '~^Doctrine\\\\DBAL\\\\Driver~',
            '~^Doctrine\\\\DBAL\\\\Logging~',
            '~^Doctrine\\\\DBAL\\\\Statement~',
            '~^Doctrine\\\\ORM\\\\AbstractQuery~',
            '~^Doctrine\\\\ORM\\\\EntityManager~',
            '~^Doctrine\\\\ORM\\\\EntityRepository~',
            '~^Doctrine\\\\ORM\\\\Query~',
            '~^Doctrine\\\\ORM\\\\Proxy~',
            '~^Doctrine\\\\ORM\\\\UnitOfWork~',
            '~^Doctrine\\\\ORM\\\\Internal~',
            '~^Doctrine\\\\ORM\\\\Persisters~',
            '~^Doctrine\\\\ORM\\\\PersistentCollection~',
        ]);
    }

    // helpers ---------------------------------------------------------------------------------------------------------

    public static function getType(string $sql): int
    {
        $intro = '(?:(?:-- [^\n]+\n)|(?:/\\*[^*]+\\*/)|\s)*+';
        if (preg_match("~^{$intro}(?:SELECT|\\()~i", $sql) !== 0) {
            return self::SELECT;
        } elseif (preg_match("~^{$intro}(?:INSERT|REPLACE)~i", $sql) !== 0) {
            return self::INSERT;
        } elseif (preg_match("~^{$intro}UPDATE~i", $sql) !== 0) {
            return self::UPDATE;
        } elseif (preg_match("~^{$intro}DELETE|TRUNCATE~i", $sql) !== 0) {
            return self::DELETE;
        } elseif (preg_match("~^{$intro}(?:BEGIN|START TRANSACTION|SAVEPOINT)~i", $sql) !== 0) {
            return self::BEGIN;
        } elseif (preg_match("~^{$intro}(?:COMMIT|RELEASE SAVEPOINT)~i", $sql) !== 0) {
            return self::COMMIT;
        } elseif (preg_match("~^{$intro}ROLLBACK~i", $sql) !== 0) {
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
        $numberColor = Ansi::colorStart(Dumper::$colors['int']);
        $stringColor = Ansi::colorStart(Dumper::$colors['string']);
        $tableColor = Ansi::colorStart(Dumper::$colors['table']);
        $reserved = implode('|', Sql::getReserved()) . '|TRUNCATE';

        // highlight table names
        $query = preg_replace_callback("~(?<=TABLE |INTO |FROM |JOIN |UPDATE LOW_PRIORITY |UPDATE |TRUNCATE )(?!LOW_PRIORITY |TABLE )([a-z0-9._]+)~i", static function (array $match) use ($tableColor, $textColor): string {
            return $tableColor . Ansi::removeColors($match[1]) . $textColor;
        }, $query);
        // highlight keywords
        $query = preg_replace("~(?<=\s|\(|^)({$reserved})(?=\s|\)|;|,|$)~i", "{$keywordColor}\\1{$textColor}", $query);
        // indent non-keywords
        $query = preg_replace("~\n  (?=[^\\e| ])~", "\n    ", $query);
        // highlight strings and numbers (skip names)
        // todo: very crude. detect numbers better
        $query = preg_replace_callback('~\'[^\']*+\'|"[^"]*+"|`[^`]*+`|0x[0-9a-f]{32}|(?<![;0-9.a-z_])(?<!\e\\[)-?[0-9]+(?:\\.[0-9]+)?(?:e[0-9]+)?~i', static function (array $match) use ($stringColor, $numberColor, $textColor): string {
            if ($match[0][0] === "'" || $match[0][0] === '"') {
                return $stringColor . Ansi::removeColors($match[0]) . $textColor;
            } elseif ($match[0][0] === '`') {
                return $match[0];
            } elseif (str_starts_with($match[0], '0x')) {
                return $stringColor . Ansi::removeColors($match[0]) . $textColor;
            } else {
                return $numberColor . Ansi::removeColors($match[0]) . $textColor;
            }
        }, $query);
        // highlight comments
        $query = preg_replace_callback("~-- [^\n]++\n~", static function (array $match) use ($commentColor, $textColor): string {
            return $commentColor . Ansi::removeColors($match[0]) . $textColor;
        }, $query);
        $query = preg_replace_callback("~/\\*[^*]++\\*/~", static function (array $match) use ($commentColor, $textColor): string {
            return $commentColor . Ansi::removeColors($match[0]) . $textColor;
        }, $query);

        return $query;
    }

    public static function stripComments(string $query): string
    {
        $query = preg_replace('~-- [^\\n]++\n~', '', $query);

        return preg_replace('~/\\*(?!\\+).*?\\*/~', '', $query);
    }

    public static function stripInsertColumnList(string $query): string
    {
        return preg_replace( // https://regex101.com/r/KQ7VYt/2
            "~(INTO [a-z0-9_]+) \\([a-z0-9_]+(?:, ?[a-z0-9_]+)*\\)~i",
            '$1 (...)',
            $query
        );
    }

    public static function stripDoctrineSelectColumns(string $query): string
    {
        $maxFieldsToKeep = 3;

        return preg_replace( // https://regex101.com/r/KQ7VYt/2
            "~
                (SELECT(?:\s+/\\*\\+[^*]++\\*/)?)\s*+
                (
                    \w++\.\w++\s*+
                    (?:AS\s*+\w++)?+\s*+
                )
                (?:,\s*+(?2)){{$maxFieldsToKeep},}\s*+
                (FROM)
            ~xi",
            '$1 ... $3',
            $query
        );
    }

    /**
     * Converts inserts from syntax `INSERT INTO ... (...) VALUES (...)` to `INSERT INTO ... SET key = val, ...`
     */
    public static function convertInsertToInsertSet(string $query, ?string $serverInfo): string
    {
        try {
            $serverInfo = $serverInfo ?? self::$defaultServerInfo;
            [$serverName, $serverVersion] = explode(' ', $serverInfo);
            if ($serverName === 'mysql' && class_exists(Parser::class, true)) {
                $platform = Platform::get($serverName, $serverVersion);
                $config = new ParserConfig($platform);
                $session = new Session($platform);
                $parser = new Parser($config, $session);
                $commands = iterator_to_array($parser->parse($query));
                if (count($commands) > 1) {
                    return $query;
                }
                $insertInto = $commands[0];
                if (!$insertInto instanceof InsertValuesCommand) {
                    return $query;
                }
                $rows = $insertInto->getRows();
                if (count($rows) > 1) {
                    return $query;
                }
                $columns = $insertInto->getColumns();
                $row = $rows[0];
                if ($columns === null || array_keys($columns) !== array_keys($row)) {
                    return $query;
                }
                $assignments = [];
                foreach ($columns as $i => $column) {
                    $assignments[] = new Assignment($column, $row[$i], Operator::EQUAL);
                }
                $insertSet = new InsertSetCommand(
                    $insertInto->getTable(),
                    $assignments,
                    $insertInto->getAlias(),
                    $insertInto->getPartitions(),
                    $insertInto->getPriority(),
                    $insertInto->getIgnore(),
                    $insertInto->getOptimizerHints(),
                    $insertInto->getOnDuplicateKeyAction()
                );
                $formatter = new Formatter($platform, $session);

                return $insertSet->serialize($formatter);
            }
        } catch (Throwable $e) {
            // pass
        }

        return $query;
    }

}
