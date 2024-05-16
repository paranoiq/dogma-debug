<?php
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

class PdoProxy extends PDO
{

    public const ATTR_AUTOCOMMIT = 0;
    public const ATTR_PREFETCH = 1;
    public const ATTR_TIMEOUT = 2;
    public const ATTR_ERRMODE = 3;
    public const ATTR_SERVER_VERSION = 4;
    public const ATTR_CLIENT_VERSION = 5;
    public const ATTR_SERVER_INFO = 6;
    public const ATTR_CONNECTION_STATUS = 7;
    public const ATTR_CASE = 8;
    public const ATTR_CURSOR_NAME = 9;
    public const ATTR_CURSOR = 10;
    public const ATTR_ORACLE_NULLS = 11;
    public const ATTR_PERSISTENT = 12;
    public const ATTR_STATEMENT_CLASS = 13;
    public const ATTR_FETCH_TABLE_NAMES = 14;
    public const ATTR_FETCH_CATALOG_NAMES = 15;
    public const ATTR_DRIVER_NAME = 16;
    public const ATTR_STRINGIFY_FETCHES = 17;
    public const ATTR_MAX_COLUMN_LEN = 18;
    public const ATTR_EMULATE_PREPARES = 20;
    public const ATTR_DEFAULT_FETCH_MODE = 19;
    public const ATTR_DEFAULT_STR_PARAM = 21;

    public const MYSQL_ATTR_USE_BUFFERED_QUERY = 1000;
    public const MYSQL_ATTR_LOCAL_INFILE = 1001;
    public const MYSQL_ATTR_INIT_COMMAND = 1002;
    public const MYSQL_ATTR_MAX_BUFFER_SIZE = 1005;
    public const MYSQL_ATTR_READ_DEFAULT_FILE = 1003;
    public const MYSQL_ATTR_READ_DEFAULT_GROUP = 1004;
    public const MYSQL_ATTR_COMPRESS = 1003;
    public const MYSQL_ATTR_DIRECT_QUERY = 1004;
    public const MYSQL_ATTR_FOUND_ROWS = 1005;
    public const MYSQL_ATTR_IGNORE_SPACE = 1006;
    public const MYSQL_ATTR_SERVER_PUBLIC_KEY = 1012;
    public const MYSQL_ATTR_SSL_KEY = 1007;
    public const MYSQL_ATTR_SSL_CERT = 1008;
    public const MYSQL_ATTR_SSL_CA = 1009;
    public const MYSQL_ATTR_SSL_CAPATH = 1010;
    public const MYSQL_ATTR_SSL_CIPHER = 1011;
    public const MYSQL_ATTR_MULTI_STATEMENTS = 1013;
    public const MYSQL_ATTR_SSL_VERIFY_SERVER_CERT = 1014;
    public const MYSQL_ATTR_LOCAL_INFILE_DIRECTORY = 1015;

    public const PGSQL_ATTR_DISABLE_PREPARES = 1000;

    public const SQLSRV_ATTR_ENCODING = 1000;
    public const SQLSRV_ATTR_QUERY_TIMEOUT = 1001;
    public const SQLSRV_ATTR_DIRECT_QUERY = 1002;
    public const SQLSRV_ATTR_CURSOR_SCROLL_TYPE = 1003;
    public const SQLSRV_ATTR_CLIENT_BUFFER_MAX_KB_SIZE = 1004;
    public const SQLSRV_ATTR_FETCHES_NUMERIC_TYPE = 1005;
    public const SQLSRV_ATTR_FETCHES_DATETIME_TYPE = 1006;
    public const SQLSRV_ATTR_FORMAT_DECIMALS = 1007;
    public const SQLSRV_ATTR_DECIMAL_PLACES = 1008;
    public const SQLSRV_ATTR_DATA_CLASSIFICATION = 1009;

    public const SQLITE_ATTR_OPEN_FLAGS = 1000;
    public const SQLITE_ATTR_READONLY_STATEMENT = 1001;
    public const SQLITE_ATTR_EXTENDED_RESULT_CODES = 1002;

    public const OCI_ATTR_ACTION = 1000;
    public const OCI_ATTR_CLIENT_INFO = 1001;
    public const OCI_ATTR_CLIENT_IDENTIFIER = 1002;
    public const OCI_ATTR_MODULE = 1003;
    public const OCI_ATTR_CALL_TIMEOUT = 1004;

    public const NAME = 'pdo';

    /** @var int */
    public static $intercept = Intercept::LOG_CALLS;

    /** @var int */
    private static $connections = 0;

    /** @var string */
    private $name;

    public function __construct($dsn, $username = null, $password = null, $options = null)
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
    public function prepare($query, $options = [])
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

    #[ReturnTypeWillChange]
    public function beginTransaction()
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

    #[ReturnTypeWillChange]
    public function commit()
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

    #[ReturnTypeWillChange]
    public function rollBack()
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

    #[ReturnTypeWillChange]
    public function inTransaction()
    {
        $result = false;
        try {
            $result = parent::inTransaction();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::inTransaction', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function setAttribute($attribute, $value)
    {
        $result = false;
        try {
            $result = parent::setAttribute($attribute, $value);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::setAttribute', [$attribute, $value], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function getAttribute($attribute)
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

    // $mode = PDO::ATTR_DEFAULT_FETCH_MODE not working in 8.2+
    #[ReturnTypeWillChange]
    public function query($query, $mode = PDO::FETCH_ASSOC, ...$fetch_mode_args)
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

    #[ReturnTypeWillChange]
    public function errorCode()
    {
        $result = null;
        try {
            $result = parent::errorCode();
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::errorCode', [], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public function errorInfo()
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
    public function quote($string, $type = PDO::PARAM_INT)
    {
        $result = false;
        try {
            $result = parent::quote($string, $type);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::quote', [$string, $type], $result);
        }

        return $result;
    }

    public function sqliteCreateFunction($function_name, $callback, $num_args = -1, $flags = 0)
    {
        $result = false;
        try {
            $result = parent::sqliteCreateFunction($function_name, $callback, $num_args, $flags);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::sqliteCreateFunction', [$function_name, $callback, $num_args, $flags], $result);
        }

        return $result;
    }

    #[ReturnTypeWillChange]
    public static function getAvailableDrivers()
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
