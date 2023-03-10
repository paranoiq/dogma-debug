<?php declare(strict_types = 1);

namespace Dogma\Debug;

use Doctrine\DBAL\Logging\SQLLogger;
use function array_map;
use function implode;
use function is_array;
use function is_string;
use function microtime;

class DoctrineSqlLogger implements SQLLogger
{

    /** @var string|null */
    private $connection;

    /** @var string */
    private $sql;

    /** @var float */
    private $start;

    public function __construct(?string $connection)
    {
        $this->connection = $connection;

        SqlHandler::log(SqlHandler::CONNECT, null, null, 0.0, $this->connection);
    }

    /**
     * @param list<int|float|bool|string|string[]|int[]|float[]|null>|null $params
     * @param list<string>|null $types
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->sql = $sql . (($params === null || $params === []) ? '' : (' -- [' . implode(', ', array_map(static function ($param) {
            if (is_array($param)) {
                return implode(',', $param);
            } elseif (is_string($param)) {
                return $param;
            } elseif ($param === true) {
                return 'TRUE';
            } elseif ($param === false) {
                return 'FALSE';
            } elseif ($param === null) {
                return 'NULL';
            } else {
                return (string) $param;
            }
        }, $params)) . ']'));
        $this->start = microtime(true);
    }

    public function stopQuery(): void
    {
        $duration = microtime(true) - $this->start;
        SqlHandler::log(SqlHandler::getType($this->sql), $this->sql, null, $duration, $this->connection);
    }

}
