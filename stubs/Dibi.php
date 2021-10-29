<?php declare(strict_types=1);

namespace Dibi;

abstract class DriverException extends \Exception
{
}

abstract class Event
{

    /** event type */
    public const CONNECT = 1;
    public const SELECT = 4;
    public const INSERT = 8;
    public const DELETE = 16;
    public const UPDATE = 32;
    public const QUERY = 60; // SELECT | INSERT | DELETE | UPDATE
    public const BEGIN = 64;
    public const COMMIT = 128;
    public const ROLLBACK = 256;
    public const TRANSACTION = 448; // BEGIN | COMMIT | ROLLBACK
    public const ALL = 1023;

    /** @var Connection */
    public $connection;

    /** @var int */
    public $type;

    /** @var string */
    public $sql;

    /** @var Result|DriverException|null */
    public $result;

    /** @var float */
    public $time;

    /** @var int */
    public $count;

    /** @var array */
    public $source;

    abstract public function __construct(Connection $connection, $type, $sql = null);

    abstract public function done($result = null);

}
