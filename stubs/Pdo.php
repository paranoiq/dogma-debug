<?php declare(strict_types = 1);

class PdoDblib extends PDO
{

}

class PdoFirebird extends PDO
{

}

class PdoMySql extends PDO
{

    public function getWarningCount(): int
    {
        return 0;
    }

}

class PdoOci extends PDO
{

}

class PdoOdbc extends PDO
{

}

class PdoPgsql extends PDO
{

    /**
     * @var int
     *
     * @cname PDO_PGSQL_ATTR_DISABLE_PREPARES
     */
    public const ATTR_DISABLE_PREPARES = 1000;

    /**
     * @var int
     *
     * @cname PGSQL_TRANSACTION_IDLE
     */
    public const TRANSACTION_IDLE = 0;

    /**
     * @var int
     *
     * @cname PGSQL_TRANSACTION_ACTIVE
     */
    public const TRANSACTION_ACTIVE = 1;

    /**
     * @var int
     *
     * @cname PGSQL_TRANSACTION_INTRANS
     */
    public const TRANSACTION_INTRANS = 2;

    /**
     * @var int
     *
     * @cname PGSQL_TRANSACTION_INERROR
     */
    public const TRANSACTION_INERROR = 3;

    /**
     * @var int
     *
     * @cname PGSQL_TRANSACTION_UNKNOWN
     */
    public const TRANSACTION_UNKNOWN = 4;

    public function escapeIdentifier(string $input): string
    {
        return '';
    }

    public function copyFromArray(
        string $tableName,
        array $rows,
        string $separator = "\t",
        string $nullAs = '\\\\N',
        ?string $fields = null,
    ): bool
    {
        return false;
    }

    public function copyFromFile(
        string $tableName,
        string $filename,
        string $separator = "\t",
        string $nullAs = '\\\\N',
        ?string $fields = null,
    ): bool
    {
        return false;
    }

    public function copyToArray(
        string $tableName,
        string $separator = "\t",
        string $nullAs = '\\\\N',
        ?string $fields = null,
    ): array|false
    {
        return false;
    }

    public function copyToFile(
        string $tableName,
        string $filename,
        string $separator = "\t",
        string $nullAs = '\\\\N',
        ?string $fields = null,
    ): bool
    {
        return false;
    }

    public function lobCreate(): string|false
    {
        return false;
    }

    // Opens an existing large object stream.  Must be called inside a transaction.

    /** @return resource|false */
    public function lobOpen(
        string $oid,
        string $mode = 'rb',
    )
    {
        return false;
    }

    public function lobUnlink(string $oid): bool
    {
        return false;
    }

    public function getNotify(
        int $fetchMode = PDO::FETCH_USE_DEFAULT,
        int $timeoutMilliseconds = 0,
    ): array|false
    {
        return false;
    }

    public function getPid(): int
    {
        return 0;
    }

}

class PdoSqlite extends PDO
{

    /**
     * @var int
     *
     * @cname SQLITE_DETERMINISTIC
     */
    public const DETERMINISTIC = 2048;

    /**
     * @var int
     *
     * @cname SQLITE_ATTR_OPEN_FLAGS
     */
    public const ATTR_OPEN_FLAGS = 1000;

    /**
     * @var int
     *
     * @cname SQLITE_OPEN_READONLY
     */
    public const OPEN_READONLY = 1;

    /**
     * @var int
     *
     * @cname SQLITE_OPEN_READWRITE
     */
    public const OPEN_READWRITE = 2;

    /**
     * @var int
     *
     * @cname SQLITE_OPEN_CREATE
     */
    public const OPEN_CREATE = 4;

    /**
     * @var int
     *
     * @cname SQLITE_ATTR_READONLY_STATEMENT
     */
    public const ATTR_READONLY_STATEMENT = 1001;

    /**
     * @var int
     *
     * @cname
     */
    public const ATTR_EXTENDED_RESULT_CODES = 1002;

    // Registers an aggregating User Defined Function for use in SQL statements
    public function createAggregate(
        string $name,
        callable $step,
        callable $finalize,
        int $numArgs = -1,
    ): bool
    {
    }

    // Registers a User Defined Function for use as a collating function in SQL statements
    public function createCollation(
        string $name,
        callable $callback,
    ): bool
    {
        return false;
    }

    public function createFunction(
        string $function_name,
        callable $callback,
        int $num_args = -1,
        int $flags = 0,
    ): bool
    {
        return false;
    }

    // Whether SQLITE_OMIT_LOAD_EXTENSION is defined or not depends on how
    // SQLite was compiled: https://www.sqlite.org/compile.html
    // ifndef SQLITE_OMIT_LOAD_EXTENSION
    public function loadExtension(string $name): bool
    {
        return false;
    }

    // endif

    public function openBlob(
        string $table,
        string $column,
        int $rowid,
        ?string $dbname = 'main', // null,
        int $flags = self::OPEN_READONLY,
    ): mixed /* resource|false */
    {
        return false;
    }

}
