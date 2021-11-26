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

    public const NAME = 'syslog';

    /** @var int */
    private static $intercept = Intercept::NONE;

    /**
     * Take control over openlog(), closelog(), syslog()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptSyslog(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'openlog', [self::class, 'fakeOpenlog']);
        Intercept::register(self::NAME, 'closelog', [self::class, 'fakeCloselog']);
        Intercept::register(self::NAME, 'syslog', [self::class, 'fakeSyslog']);
        self::$intercept = $level;
    }

    public static function fakeOpenlog(string $prefix, int $flags, int $facility): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, 'openlog', [$prefix, $flags, $facility], true);
    }

    public static function fakeCloselog(): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, 'closelog', [], true);
    }

    public static function fakeSyslog(int $priority, string $message): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, 'syslog', [$priority, $message], true);
    }

}
