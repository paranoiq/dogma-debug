<?php declare(strict_types = 1);

namespace Dogma\Debug;

use Doctrine\DBAL\Logging\SQLLogger;
use function implode;
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
     * @param array|null $params
     * @param array|null $types
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->sql = $sql . (($params === null || $params === []) ? '' : (' [' . implode(', ', $params) . ']'));
        $this->start = microtime(true);
    }

    public function stopQuery(): void
    {
        $duration = microtime(true) - $this->start;
        SqlHandler::log(SqlHandler::getType($this->sql), $this->sql, null, $duration, $this->connection);
    }

}
