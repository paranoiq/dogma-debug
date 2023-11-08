<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use JetBrains\PhpStorm\Internal\LanguageLevelTypeAware;
use PDO;
use PDOException;
use PDOStatement;
use function array_merge;
use function microtime;

class FakePdoStatement extends PDOStatement
{

    public const NAME = 'PdoStatement';

    /** @var int */
    public static $intercept = Intercept::SILENT;

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

        return parent::bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    public function bindValue(
        int|string $param,
        mixed $value,
        int $type = PDO::PARAM_STR
    ): bool {
        $this->params[$param] = $value;

        return parent::bindValue($param, $value, $type);
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
            Intercept::log(self::NAME, self::$intercept, 'PDO::prepare', [$params], $result);
            if (!$logged) {
                SqlHandler::logPdoStatementExecute($this, $allParams, $this->rowCount(), $t);
            }
        }

        return $result;
    }

}
