<?php declare(strict_types = 1);

namespace Consistence\Enum;

abstract class Enum
{

    /**
     * @return string[]|int[]
     */
    abstract public static function getAvailableValues(): array;

    /**
     * @return int|string
     */
    abstract public function getValue();

}

abstract class MultiEnum
{

    /**
     * @return string[]|int[]
     */
    abstract public static function getAvailableValues(): array;

    /**
     * @return int|string
     */
    abstract public function getValue();

}
