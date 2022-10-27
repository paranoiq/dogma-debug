<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use PDOStatement;

class FakePdoStatement extends PDOStatement
{

    public const NAME = 'PdoStatement';

    /** @var int */
    public static $intercept = Intercept::LOG_CALLS;

    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        $result = false;
        try {
            $result = parent::execute($params);
        } finally {
            Intercept::log(self::NAME, self::$intercept, 'PDO::prepare', [$params], $result);
        }

        return $result;
    }

}
