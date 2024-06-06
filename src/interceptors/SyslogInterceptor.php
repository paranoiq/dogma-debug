<?php
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
class SyslogInterceptor
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
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for syslog functions.");
        }

        Intercept::registerFunction(self::NAME, 'openlog', self::class);
        Intercept::registerFunction(self::NAME, 'closelog', self::class);
        Intercept::registerFunction(self::NAME, 'syslog', self::class);
        self::$intercept = $level;
    }

    public static function openlog(string $prefix, int $flags, int $facility): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$prefix, $flags, $facility], true);
    }

    public static function closelog(): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [], true);
    }

    public static function syslog(int $priority, string $message): bool
    {
        return Intercept::handle(self::NAME, self::$intercept, __FUNCTION__, [$priority, $message], true);
    }

}
