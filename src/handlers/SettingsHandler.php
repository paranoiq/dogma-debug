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

    /** @var int */
    private static $takeoverIni = Takeover::NONE;

    /** @var int */
    private static $takeoverEnv = Takeover::NONE;

    // takeover handlers -----------------------------------------------------------------------------------------------

    /**
     * Take control over ini_set(), ini_alter(), ini_restore()
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeoverIni(int $level): void
    {
        Takeover::register('settings', 'ini_set', [self::class, 'fakeIniSet']);
        Takeover::register('settings', 'ini_alter', [self::class, 'fakeIniSet']);
        Takeover::register('settings', 'ini_restore', [self::class, 'fakeIniRestore']);
        self::$takeoverIni = $level;
    }

    /**
     * Take control over putenv()
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeoverEnv(int $level): void
    {
        Takeover::register('settings', 'putenv', [self::class, 'fakePutenv']);
        self::$takeoverEnv = $level;
    }

    public static function fakeIniSet(string $name, string $value): void
    {
        Takeover::handle('settings', self::$takeoverIni, 'ini_set', [$name, $value], ini_get($name));
    }

    public static function fakeIniRestore(string $name): void
    {
        Takeover::handle('settings', self::$takeoverIni, 'ini_restore', [$name], null);
    }

    public static function fakePutenv(string $assignment): bool
    {
        return Takeover::handle('settings', self::$takeoverEnv, 'putenv', [$assignment], true);
    }

}
