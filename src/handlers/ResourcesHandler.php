<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function function_exists;
use function microtime;
use function number_format;
use function pcntl_alarm;
use function pcntl_signal;
use function rd;
use const SIG_DFL;
use const SIGALRM;

/**
 * Monitors system resources (time, memory)
 */
class ResourcesHandler
{

    /** @var int Timeout between monitor events [seconds] */
    public static $monitorTimeout = 1;

    /** @var int|null Extend time limit - keep up running, but report that time limit would be reached */
    //public static $extendTimeLimit;

    /** @var int|null Extend memory limit - keep app running, but report that memory limit would be reached */
    //public static $extendMemoryLimit;

    /** @var float Terminate when getting close to memory limit */
    //public static $terminateOnMemoryLimitUsed = 0.9;

    /** @var bool Periodically report resources usage (memory, processor, time spent) */
    public static $reportResourcesUsage = false;

    /** @var int Report on each n-th monitor event */
    public static $resourcesUsageReportRatio = 5;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var bool */
    private static $enabled;

    /** @var int */
    private static $takeoverAlarm = Takeover::NONE;

    /** @var int */
    private static $takeoverTimeLimit = Takeover::NONE;

    /** @var int */
    private static $takeoverSleep = Takeover::NONE;

    /** @var float */
    private static $extendedTime = 0.0;

    /** @var float */
    private static $sleptTime = 0.0;

    /** @var Resources */
    private static $resources;

    public static function enable(?bool $report = null, int $ratio = 5): void
    {
        if (System::isWindows() || !function_exists('pcntl_alarm')) {
            return;
        }
        if ($report !== null) {
            self::$reportResourcesUsage = $report;
        }
        self::$resourcesUsageReportRatio = $ratio;

        self::$resources = Resources::get();

        pcntl_alarm(self::$monitorTimeout);
        pcntl_signal(SIGALRM, [self::class, 'monitor']);

        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function monitor(): void
    {
        if (!self::$enabled) {
            // deactivate after last signal
            pcntl_signal(SIGALRM, SIG_DFL);
        }

        static $counter = 0;

        if (Resources::timeRemaining() <= self::$monitorTimeout) { // last time before timeout
            Debugger::setTermination('time limit (' . Resources::timeLimit() . ' s)');
        }

        if (Resources::memoryRemainingRatio() < 0.1) { // 90% used
            Debugger::setTermination('memory limit (' . Dumper::size(Resources::memoryLimit()) . ')');
        }

        // todo: proc
        $resources = Resources::get();
        rd($resources);
        $diff = $resources->diff(self::$resources);
        rd($diff);

        $counter++;
        if ($counter >= self::$resourcesUsageReportRatio && self::$reportResourcesUsage) {
            $counter = 0;
            $timeFormatted = number_format($resources->time, 1);
            $memFormatted = Dumper::size($resources->phpMemory);
            Debugger::send(Packet::ERROR, Ansi::dyellow("Running $timeFormatted s, $memFormatted"));
        }

        pcntl_alarm(self::$monitorTimeout);
    }

    // takeover handlers -----------------------------------------------------------------------------------------------

    public static function takeoverAlarm(int $level = Takeover::LOG_OTHERS): void
    {
        self::$takeoverAlarm = $level;
        Takeover::register('resources', 'pcntl_alarm', [self::class, 'fakeAlarm']);
    }

    public static function takeoverTimeLimit(int $level = Takeover::LOG_OTHERS): void
    {
        self::$takeoverTimeLimit = $level;
        Takeover::register('resources', 'set_time_limit', [self::class, 'fakeTimeLimit']);
    }

    public static function takeoverSleep(int $level = Takeover::LOG_OTHERS): void
    {
        self::$takeoverSleep = $level;
        Takeover::register('resources', 'sleep', [self::class, 'fakeSleep']);
        Takeover::register('resources', 'usleep', [self::class, 'fakeUsleep']);
        Takeover::register('resources', 'time_nanosleep', [self::class, 'fakeNanosleep']);
        Takeover::register('resources', 'time_sleep_until', [self::class, 'fakeSleepUntil']);
    }

    public static function fakeAlarm(int $seconds): int
    {
        return Takeover::handle('resources', self::$takeoverAlarm, 'pcntl_alarm', [$seconds], 0);
    }

    public static function fakeTimeLimit(int $seconds): bool
    {
        if (self::$takeoverTimeLimit !== Takeover::PREVENT_OTHERS) {
            self::$extendedTime += Resources::timeUsed();
        }

        return Takeover::handle('resources', self::$takeoverTimeLimit, 'set_time_limit', [$seconds], true);
    }

    /**
     * @return int|false
     */
    public static function fakeSleep(int $seconds)
    {
        if (self::$takeoverSleep !== Takeover::PREVENT_OTHERS) {
            self::$sleptTime += $seconds;
        }

        return Takeover::handle('resources', self::$takeoverSleep, 'sleep', [$seconds], 0);
    }

    public static function fakeUsleep(int $microseconds): void
    {
        if (self::$takeoverSleep !== Takeover::PREVENT_OTHERS) {
            self::$sleptTime += ($microseconds / 1000000);
        }

        Takeover::handle('resources', self::$takeoverSleep, 'usleep', [$microseconds], null);
    }

    /**
     * @return bool|int[]
     */
    public static function fakeNanosleep(int $seconds, int $nanoseconds)
    {
        if (self::$takeoverSleep !== Takeover::PREVENT_OTHERS) {
            self::$sleptTime += $seconds + ($nanoseconds / 1000000000);
        }

        return Takeover::handle('resources', self::$takeoverSleep, 'time_nanosleep', [$seconds, $nanoseconds], true);
    }

    public static function fakeSleepUntil(float $timestamp): bool
    {
        if (self::$takeoverSleep !== Takeover::PREVENT_OTHERS) {
            self::$sleptTime += min(0, $timestamp - microtime(true));
        }

        return Takeover::handle('resources', self::$takeoverSleep, 'time_sleep_until', [$timestamp], true);
    }

}
