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
 * Tracks writes to system log
 */
class SyslogHandler
{

    /** @var int */
    private static $takeover = Takeover::NONE;

    // takeover handlers -----------------------------------------------------------------------------------------------

    /**
     * Take control over openlog(), closelog(), syslog()
     *
     * @param int $level Takeover::NONE|Takeover::LOG_OTHERS|Takeover::PREVENT_OTHERS
     */
    public static function takeoverIni(int $level): void
    {
        Takeover::register('syslog', 'openlog', [self::class, 'fakeOpenlog']);
        Takeover::register('syslog', 'closelog', [self::class, 'fakeCloselog']);
        Takeover::register('syslog', 'syslog', [self::class, 'fakeSyslog']);
        self::$takeover = $level;
    }

    public static function fakeOpenlog(string $prefix, int $flags, int $facility): bool
    {
        return Takeover::handle('syslog', self::$takeover, 'openlog', [$prefix, $flags, $facility], true);
    }

    public static function fakeCloselog(): bool
    {
        return Takeover::handle('syslog', self::$takeover, 'closelog', [], true);
    }

    public static function fakeSyslog(int $priority, string $message): bool
    {
        return Takeover::handle('syslog', self::$takeover, 'syslog', [$priority, $message], true);
    }

}
