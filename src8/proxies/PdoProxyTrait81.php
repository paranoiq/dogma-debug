<?php declare(strict_types = 1);

/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use PDO;
use PDOException;
use ReturnTypeWillChange;
use function microtime;

trait PdoProxyTrait
{

    public static int $intercept = Intercept::SILENT;

    private static int $connections = 0;

    private string $name;

    public function __construct(
        $dsn,
        $username = null,
        $password = null,
        $options = null,
    )
    {
        $this->name = 'pdo' . (++self::$connections);

        try {
            $t = microtime(true);

            parent::__construct($dsn, $username, $password, $options);
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, self::$intercept, 'PDO::__construct', [$dsn, $username, $password, $options], null);
            SqlHandler::log(SqlHandler::CONNECT, null, $t, null, null, $this->name);
        }

        $this->setAttribute(self::ATTR_STATEMENT_CLASS, [PdoStatementProxy::class]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    #[ReturnTypeWillChange]
    public function prepare(
        $query,
        $options = [],
    )
    {
        $statement = false;
        try {
            /** @var PdoStatementProxy $statement */
            $statement = parent::prepare($query, $options);
            $statement->setConnection($this);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::prepare', [$query, $options], $statement);
            // todo: SqlHandler::logPrepare($query, $options);
        }

        return $statement;
    }

    public function beginTransaction(): bool
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::beginTransaction();
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, self::$intercept, 'PDO::beginTransaction', [], $result);
            SqlHandler::log(SqlHandler::BEGIN, 'PDO::beginTransaction()', $t, null, null, $this->name);
        }

        return $result;
    }

    public function commit(): bool
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::commit();
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, self::$intercept, 'PDO::commit', [], $result);
            SqlHandler::log(SqlHandler::COMMIT, 'PDO::commit()', $t, null, null, $this->name);
        }

        return $result;
    }

    public function rollBack(): bool
    {
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::rollBack();
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, self::$intercept, 'PDO::rollBack', [], $result);
            SqlHandler::log(SqlHandler::ROLLBACK, 'PDO::rollback()', $t, null, null, $this->name);
        }

        return $result;
    }

    public function inTransaction(): bool
    {
        $result = false;
        try {
            $result = parent::inTransaction();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::inTransaction', [], $result);
        }

        return $result;
    }

    public function setAttribute(
        int $attribute,
        mixed $value,
    ): bool
    {
        $result = false;
        try {
            $result = parent::setAttribute($attribute, $value);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::setAttribute', [$attribute, $value], $result);
        }

        return $result;
    }

    public function getAttribute(int $attribute): mixed
    {
        $result = null;
        try {
            $result = parent::getAttribute($attribute);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::getAttribute', [$attribute], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function exec($statement)
    {
        $logged = false;
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::exec($statement);
        } catch (PDOException $e) {
            $t = microtime(true) - $t;
            SqlHandler::logUnknown($statement, $t, null, null, $this->name, null, $e->getMessage(), $e->getCode());
            $logged = true;
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, self::$intercept, 'PDO::exec', [$statement], $result);
            if (!$logged) {
                SqlHandler::logUnknown($statement, $t, null, null, $this->name);
            }
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function query(
        ?string $query,
        ?int $mode = null,
        mixed ...$fetch_mode_args,
    )
    {
        $logged = false;
        $result = false;
        try {
            $t = microtime(true);
            if ($mode === null) {
                $result = parent::query($query);
            } else {
                $result = parent::query($query, $mode, ...$fetch_mode_args);
            }
        } catch (PDOException $e) {
            $t = microtime(true) - $t;
            SqlHandler::logUnknown($query, $t, null, null, $this->name, null, $e->getMessage(), $e->getCode());
            $logged = true;
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, self::$intercept, 'PDO::query', [$query, $mode, ...$fetch_mode_args], $result);
            if (!$logged) {
                SqlHandler::logUnknown($query, $t, null, null, $this->name);
            }
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function lastInsertId($name = null)
    {
        $result = false;
        try {
            $result = parent::lastInsertId($name);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::lastInsertId', [$name], $result);
        }

        return $result;
    }

    public function errorCode(): ?string
    {
        $result = null;
        try {
            $result = parent::errorCode();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::errorCode', [], $result);
        }

        return $result;
    }

    public function errorInfo(): array
    {
        $result = [];
        try {
            $result = parent::errorInfo();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::errorInfo', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function quote(
        $string,
        $type = PDO::PARAM_INT,
    )
    {
        $result = false;
        try {
            $result = parent::quote($string, $type);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::quote', [$string, $type], $result);
        }

        return $result;
    }

    public function sqliteCreateFunction(
        $function_name,
        $callback,
        $num_args = -1,
        $flags = 0,
    )
    {
        $result = false;
        try {
            $result = parent::sqliteCreateFunction($function_name, $callback, $num_args, $flags);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::sqliteCreateFunction', [$function_name, $callback, $num_args, $flags], $result);
        }

        return $result;
    }

    public static function getAvailableDrivers(): array
    {
        $result = [];
        try {
            $result = parent::getAvailableDrivers();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::getAvailableDrivers', [], $result);
        }

        return $result;
    }

}
