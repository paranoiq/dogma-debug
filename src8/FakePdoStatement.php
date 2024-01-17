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

class FakePdoStatement extends PDOStatement
{

    public const NAME = 'pdo';

    /** @var int */
    public static $interceptExec = Intercept::SILENT;

    /** @var int */
    public static $interceptBind = Intercept::SILENT;

    /** @var array<int|string, mixed> */
    private $params = [];

    protected function __construct()
    {
    }

    public function bindParam(
        int|string $param,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = null,
        mixed $driverOptions = null
    ): bool {
        $this->params[$param] = $var;

        $result = false;
        try {
            $result = parent::bindParam($param, $var, $type, $maxLength, $driverOptions);
        } finally {
            Intercept::log(self::NAME, self::$interceptBind, 'PDOStatement::bindParam', [$param, $var, $type, $maxLength, $driverOptions], $result);
        }

        return $result;
    }

    public function bindValue(
        int|string $param,
        mixed $value,
        int $type = PDO::PARAM_STR
    ): bool {
        $this->params[$param] = $value;

        $result = false;
        try {
            $result = parent::bindValue($param, $value, $type);
        } finally {
            Intercept::log(self::NAME, self::$interceptBind, 'PDOStatement::bindValue', [$param, $value, $type], $result);
        }

        return $result;
    }

    public function execute(?array $params = null): bool
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
            Intercept::log(self::NAME, self::$interceptExec, 'PDOStatement::execute', [$params], $result);
            if (!$logged) {
                SqlHandler::logPdoStatementExecute($this, $allParams, $this->rowCount(), $t);
            }
        }

        return $result;
    }

}
