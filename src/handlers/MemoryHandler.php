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
 * Watches over GC
 * Searches memory for objects
 */
class MemoryHandler
{

    /** @var int */
    private static $takeover = Takeover::NONE;

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

    // takeover handlers -----------------------------------------------------------------------------------------------

    public static function takeoverGc(int $level = Takeover::LOG_OTHERS): void
    {
        Takeover::register('memory', 'gc_enable', [self::class, 'fakeEnable']);
        Takeover::register('memory', 'gc_disable', [self::class, 'fakeDisable']);
        Takeover::register('memory', 'gc_collect_cycles', [self::class, 'fakeCollect']);
        Takeover::register('memory', 'gc_mem_caches', [self::class, 'fakeCaches']);

        self::$takeover = $level;
    }

    public static function fakeEnable(): void
    {
        Takeover::handle('memory', self::$takeover, 'gc_enable', [], null);
    }

    public static function fakeDisable(): void
    {
        Takeover::handle('memory', self::$takeover, 'gc_disable', [], null);
    }

    public static function fakeCollect(): int
    {
        return Takeover::handle('memory', self::$takeover, 'gc_collect_cycles', [], 0);
    }

    public static function fakeCaches(): int
    {
        return Takeover::handle('memory', self::$takeover, 'gc_mem_caches', [], 0);
    }

}
