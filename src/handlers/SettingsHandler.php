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

/**
 * Tracks changes in php settings and environment variables
 */
class SettingsHandler
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
        Intercept::register(self::NAME, 'ini_set', [self::class, 'fakeIniSet']);
        Intercept::register(self::NAME, 'ini_alter', [self::class, 'fakeIniSet']);
        Intercept::register(self::NAME, 'ini_restore', [self::class, 'fakeIniRestore']);
        self::$interceptIni = $level;
    }

    /**
     * Take control over putenv()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptEnv(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'putenv', [self::class, 'fakePutenv']);
        self::$interceptEnv = $level;
    }

    public static function fakeIniSet(string $name, string $value): void
    {
        Intercept::handle(self::NAME, self::$interceptIni, 'ini_set', [$name, $value], ini_get($name));
    }

    public static function fakeIniRestore(string $name): void
    {
        Intercept::handle(self::NAME, self::$interceptIni, 'ini_restore', [$name], null);
    }

    public static function fakePutenv(string $assignment): bool
    {
        return Intercept::handle(self::NAME, self::$interceptEnv, 'putenv', [$assignment], true);
    }

}
