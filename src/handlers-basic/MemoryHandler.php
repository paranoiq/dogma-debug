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

    public const NAME = 'memory';

    /** @var int */
    private static $intercept = Intercept::NONE;

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

    // intercept handlers ----------------------------------------------------------------------------------------------

    /**
     * Takes control over gc_enable(), gc_disable(), gc_collect_cycles() and gc_mem_caches()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptGarbageCollection(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'gc_enable', [self::class, 'fakeEnable']);
        Intercept::register(self::NAME, 'gc_disable', [self::class, 'fakeDisable']);
        Intercept::register(self::NAME, 'gc_collect_cycles', [self::class, 'fakeCollect']);
        Intercept::register(self::NAME, 'gc_mem_caches', [self::class, 'fakeCaches']);

        self::$intercept = $level;
    }

    public static function fakeEnable(): void
    {
        Intercept::handle(self::NAME, self::$intercept, 'gc_enable', [], null);
    }

    public static function fakeDisable(): void
    {
        Intercept::handle(self::NAME, self::$intercept, 'gc_disable', [], null);
    }

    public static function fakeCollect(): int
    {
        return Intercept::handle(self::NAME, self::$intercept, 'gc_collect_cycles', [], 0);
    }

    public static function fakeCaches(): int
    {
        return Intercept::handle(self::NAME, self::$intercept, 'gc_mem_caches', [], 0);
    }

}
