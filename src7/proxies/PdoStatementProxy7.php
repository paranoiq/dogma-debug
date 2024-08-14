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
use PDOStatement;
use function array_merge;
use function microtime;

class PdoStatementProxy extends PDOStatement
{

    public const NAME = 'pdo';

    /** @var int */
    public static $interceptExec = Intercept::SILENT;

    /** @var int */
    public static $interceptBind = Intercept::SILENT;

    /** @var PdoProxy */
    private $connection;

    /** @var array<int|string, mixed> */
    private $params = [];

    protected function __construct()
    {
    }

    public function setConnection(PdoProxy $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @param int|string $param
     * @param mixed $var
     * @param int $type
     * @param int $maxLength
     * @param mixed $driverOptions
     */
    public function bindParam($param, &$var, $type = PDO::PARAM_STR, $maxLength = null, $driverOptions = null): bool {
        $this->params[$param] = $var;

        return parent::bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    /**
     * @param int|string $param
     * @param mixed $value
     * @param int $type
     */
    public function bindValue($param, $value, $type = PDO::PARAM_STR): bool {
        $this->params[$param] = $value;

        return parent::bindValue($param, $value, $type);
    }

    /**
     * @param array|null $params
     */
    public function execute($params = null): bool
    {
        $allParams = $params !== null ? array_merge($this->params, $params) : $this->params;

        $logged = false;
        $result = false;
        try {
            $t = microtime(true);
            $result = parent::execute($params);
        } catch (PDOException $e) {
            $t = microtime(true) - $t;
            SqlHandler::logPdoStatementExecute($this, $allParams, 0, $t, null, $e->getMessage(), $e->getCode());
            $logged = true;
        } finally {
            $t = microtime(true) - $t;
            Intercept::log(self::NAME, self::$interceptExec, 'PDO::prepare', [$params], $result);
            if (!$logged) {
                SqlHandler::logPdoStatementExecute($this, $allParams, $t, $this->rowCount(), $this->connection->lastInsertId(), $this->connection->getName());
            }
        }

        return $result;
    }

}
