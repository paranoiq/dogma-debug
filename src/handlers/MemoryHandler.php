<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

/**
 * Searches memory for objects
 */
class MemoryHandler
{

    public const NAME = 'memory';

    /**
     * Searches object in memory (global, class static, function static) and returns path to given object.
     * Can be used to track memory leaks
     *
     * @param object $object
     */
    public static function find($object): string
    {
        // todo
        return '';
    }

}
