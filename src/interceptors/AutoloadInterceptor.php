<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function ini_get;
use function spl_autoload_extensions;

class AutoloadInterceptor
{
    public const NAME = 'autoload';

    /** @var int */
    private static $intercept = Intercept::NONE;

    /** @var string|null */
    private static $unserializeCallback;

    /**
     * Take control over majority autoloading functions
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptAutoload(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'spl_autoload_register', self::class);
        Intercept::registerFunction(self::NAME, 'spl_autoload_unregister', self::class);
        Intercept::registerFunction(self::NAME, 'spl_autoload_extensions', self::class);
        Intercept::registerFunction(self::NAME, 'spl_autoload_functions', self::class);
        Intercept::registerFunction(self::NAME, 'spl_autoload_call', self::class);
        Intercept::registerFunction(self::NAME, 'spl_autoload', self::class);

        // optional user defined function with default implementation being spl_autoload(). deprecated since 7.2, disabled since 8.0
        Intercept::registerFunction(self::NAME, '__autoload', self::class);

        // unserialize_callback_func ini setting
        self::$unserializeCallback = ini_get('unserialize_callback_func');
        if (self::$unserializeCallback !== false) {
            // todo: can this be a static method?
            Intercept::registerFunction(self::NAME, self::$unserializeCallback, [self::class, 'fakeUnserializeCallback']);
        }

        self::$intercept = $level;
    }

    public static function spl_autoload_register(?callable $callback = null, bool $throw = true, bool $prepend = false): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$callback, $throw, $prepend], true);
    }

    public static function spl_autoload_unregister(callable $callback): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$callback], true);
    }

    public static function spl_autoload_extensions(?string $file_extensions = null): string
    {
        $default = spl_autoload_extensions();

        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$file_extensions], $default);
    }

    public static function spl_autoload_functions(): string
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], []);
    }

    public static function spl_autoload_call(string $class): void
    {
        Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$class], null);
    }

    public static function spl_autoload(string $class, ?string $file_extensions = null): void
    {
        Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$class, $file_extensions], null);
    }

    public static function __autoload(string $class): void
    {
        Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$class], null);
    }

    public static function fakeUnserializeCallback(string $class): void
    {
        Intercept::handle(self::NAME, self::$intercept, self::$unserializeCallback, [$class], null);
    }

}
