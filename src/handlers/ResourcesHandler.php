<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use const SIG_DFL;
use const SIGALRM;
use function func_get_args;
use function function_exists;
use function microtime;
use function min;
use function number_format;
use function pcntl_alarm;
use function pcntl_signal;
use function rd;
use function register_tick_function;
use function unregister_tick_function;

/**
 * Monitors system resources (time, memory)
 */
class ResourcesHandler
{

    public const NAME = 'resources';

    /** @var int Timeout between monitor events [seconds] */
    public static $monitorTimeout = 1;

    /** @var int|null Extend time limit - keep up running, but report that time limit would be reached */
    //public static $extendTimeLimit;

    /** @var int|null Extend memory limit - keep app running, but report that memory limit would be reached */
    //public static $extendMemoryLimit;

    /** @var float Terminate when getting close to memory limit */
    //public static $terminateOnMemoryLimitUsed = 0.9;

    /** @var int|null Timeout between resources usage report [seconds] */
    public static $reportTimeout;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var bool */
    private static $enabled;

    /** @var int */
    private static $interceptAlarm = Intercept::NONE;

    /** @var int */
    private static $interceptTicks = Intercept::NONE;

    /** @var int */
    private static $interceptTimeLimit = Intercept::NONE;

    /** @var int */
    private static $interceptSleep = Intercept::NONE;

    /** @var int */
    private static $alarmCounter = 0;

    /** @var int */
    private static $tickCounter = 0;

    /** @var float */
    private static $lastReportTime;

    /** @var float */
    private static $extendedTime = 0.0;

    /** @var float */
    private static $sleptTime = 0.0;

    /** @var Resources */
    private static $resources;

    public static function enable(?int $reportTimeout = 5, int $ticks = 2000): void
    {
        if (function_exists('pcntl_alarm')) {
            self::enableAlarm($reportTimeout);
        } else {
            self::enableTicks($reportTimeout, $ticks);
        }
    }

    public static function enableAlarm(?int $reportTimeout = 5): void
    {
        if (System::isWindows()) {
            Debugger::dependencyInfo('Cannot use resources tracking via pcntl_alarm() on Windows, because pcntl extension is not available here.');
            return;
        }
        if (!function_exists('pcntl_alarm')) {
            Debugger::dependencyInfo('Cannot enable resources tracking via pcntl_alarm(), because pcntl extension is not available.');
            return;
        }

        self::$reportTimeout = $reportTimeout;
        self::$resources = Resources::get();
        self::$lastReportTime = Debugger::getStart();

        pcntl_alarm(self::$monitorTimeout);
        pcntl_signal(SIGALRM, [self::class, 'alarm']);

        self::$enabled = true;
    }

    public static function enableTicks(?int $reportTimeout = 5, int $ticks = 2000): void
    {
        self::$reportTimeout = $reportTimeout;
        self::$resources = Resources::get();
        self::$lastReportTime = Debugger::getStart();

        register_tick_function([self::class, 'tick']);

        Intercept::insertDeclareTicks($ticks);
    }

    public static function disable(): void
    {
        self::$enabled = false;
        unregister_tick_function([self::class, 'tick']);
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    /**
     * @internal
     */
    public static function alarm(): void
    {
        if (!self::$enabled) {
            // deactivate after last signal
            pcntl_signal(SIGALRM, SIG_DFL);
        }

        self::$alarmCounter++;
        self::checkTermination();
        self::monitor();

        if (!self::$enabled) {
            pcntl_alarm(self::$monitorTimeout);
        }
    }

    /**
     * @internal
     */
    public static function tick(): void
    {
        self::$tickCounter++;
        self::checkTermination();
        self::monitor();
    }

    private static function checkTermination(): void
    {
        if (Resources::timeLimit() > 0.0 && Resources::timeRemaining() <= self::$monitorTimeout) { // last time before timeout
            Debugger::setTermination('time limit (' . Resources::timeLimit() . ' s)');
        }

        if (Resources::memoryRemainingRatio() < 0.1) { // 90% used
            Debugger::setTermination('memory limit (' . Dumper::size(Resources::memoryLimit()) . ')');
        }
    }

    private static function monitor(): void
    {
        $now = microtime(true);
        if (self::$reportTimeout === null || (self::$lastReportTime + self::$reportTimeout > $now)) {
            return;
        }

        // todo: proc
        $resources = Resources::get();
        rd($resources);
        $diff = $resources->diff(self::$resources);
        rd($diff);
        self::$resources = $resources;
        self::$lastReportTime = $now;

        $timeFormatted = number_format($resources->time - Debugger::getStart(), 1);
        $memFormatted = Dumper::size($resources->phpMemory);
        Debugger::send(Packet::ERROR, Ansi::dyellow("Running $timeFormatted s, $memFormatted"));
    }

    // intercept handlers ----------------------------------------------------------------------------------------------

    /**
     * Take control over pcntl_alarm()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptAlarm(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'pcntl_alarm', [self::class, 'fakeAlarm']);
        self::$interceptAlarm = $level;
    }

    /**
     * Take control over register_tick_function() and unregister_tick_function()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptTicks(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'register_tick_function', [self::class, 'fakeRegister']);
        Intercept::register(self::NAME, 'unregister_tick_function', [self::class, 'fakeUnregister']);
        self::$interceptTicks = $level;
    }

    /**
     * Take control over set_time_limit()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptTimeLimit(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'set_time_limit', [self::class, 'fakeTimeLimit']);
        self::$interceptTimeLimit = $level;
    }

    /**
     * Take control over sleep(), usleep(), time_nanosleep() and time_sleep_until()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptSleep(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'sleep', [self::class, 'fakeSleep']);
        Intercept::register(self::NAME, 'usleep', [self::class, 'fakeUsleep']);
        Intercept::register(self::NAME, 'time_nanosleep', [self::class, 'fakeNanosleep']);
        Intercept::register(self::NAME, 'time_sleep_until', [self::class, 'fakeSleepUntil']);
        self::$interceptSleep = $level;
    }

    public static function fakeAlarm(int $seconds): int
    {
        return Intercept::handle(self::NAME, self::$interceptAlarm, 'pcntl_alarm', [$seconds], 0);
    }

    /**
     * @param mixed ...$args
     */
    public static function fakeRegister(callable $callback, ...$args): bool
    {
        return Intercept::handle(self::NAME, self::$interceptTicks, 'pcntl_alarm', func_get_args(), true);
    }

    public static function fakeUnregister(callable $callback): void
    {
        Intercept::handle(self::NAME, self::$interceptTicks, 'unregister_tick_function', [$callback], null);
    }

    public static function fakeTimeLimit(int $seconds): bool
    {
        if (self::$interceptTimeLimit !== Intercept::PREVENT_CALLS) {
            self::$extendedTime += Resources::timeUsed();
        }

        return Intercept::handle(self::NAME, self::$interceptTimeLimit, 'set_time_limit', [$seconds], true);
    }

    /**
     * @return int|false
     */
    public static function fakeSleep(int $seconds)
    {
        if (self::$interceptSleep !== Intercept::PREVENT_CALLS) {
            self::$sleptTime += $seconds;
        }

        return Intercept::handle(self::NAME, self::$interceptSleep, 'sleep', [$seconds], 0);
    }

    public static function fakeUsleep(int $microseconds): void
    {
        if (self::$interceptSleep !== Intercept::PREVENT_CALLS) {
            self::$sleptTime += ($microseconds / 1000000);
        }

        Intercept::handle(self::NAME, self::$interceptSleep, 'usleep', [$microseconds], null);
    }

    /**
     * @return bool|int[]
     */
    public static function fakeNanosleep(int $seconds, int $nanoseconds)
    {
        if (self::$interceptSleep !== Intercept::PREVENT_CALLS) {
            self::$sleptTime += $seconds + ($nanoseconds / 1000000000);
        }

        return Intercept::handle(self::NAME, self::$interceptSleep, 'time_nanosleep', [$seconds, $nanoseconds], true);
    }

    public static function fakeSleepUntil(float $timestamp): bool
    {
        if (self::$interceptSleep !== Intercept::PREVENT_CALLS) {
            self::$sleptTime += min(0, $timestamp - microtime(true));
        }

        return Intercept::handle(self::NAME, self::$interceptSleep, 'time_sleep_until', [$timestamp], true);
    }

}
