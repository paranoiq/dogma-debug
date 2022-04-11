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
use function pcntl_alarm;
use function pcntl_signal;
use function rd;
use function register_tick_function;
use function unregister_tick_function;
use const SIG_DFL;
use const SIGALRM;

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
    private static $enabled = false;

    /** @var int */
    private static $alarmCounter = 0;

    /** @var int */
    private static $tickCounter = 0;

    /** @var float */
    private static $lastReportTime;

    /** @var float */
    public static $extendedTime = 0.0;

    /** @var float */
    public static $sleptTime = 0.0;

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
            Debugger::setTermination('memory limit (' . Units::size(Resources::memoryLimit()) . ')');
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

        $time = Units::time($resources->time - Debugger::getStart());
        $memory = Units::size($resources->phpMemory);
        Debugger::send(Packet::ERROR, Ansi::dyellow("Running $time, $memory"));
    }

}
