<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function ini_get;

/**
 * Tracks changes in php settings and environment variables
 */
class SettingsInterceptor
{

    public const NAME = 'settings';

    /** @var int */
    private static $interceptIni = Intercept::NONE;

    /** @var int */
    private static $interceptEnv = Intercept::NONE;

    /**
     * Take control over ini_set(), ini_alter(), ini_restore()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptIni(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'ini_set', self::class);
        Intercept::registerFunction(self::NAME, 'ini_alter', [self::class, 'ini_set']); // alias ^
        Intercept::registerFunction(self::NAME, 'ini_restore', self::class);
        self::$interceptIni = $level;
    }

    /**
     * Take control over putenv()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptEnv(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'putenv', self::class);
        self::$interceptEnv = $level;
    }

    public static function ini_set(string $name, string $value): void
    {
        Intercept::handle(self::NAME, self::$interceptIni, __FUNCTION__, [$name, $value], ini_get($name));
    }

    public static function ini_restore(string $name): void
    {
        Intercept::handle(self::NAME, self::$interceptIni, __FUNCTION__, [$name], null);
    }

    public static function putenv(string $assignment): bool
    {
        return Intercept::handle(self::NAME, self::$interceptEnv, __FUNCTION__, [$assignment], true);
    }

}
