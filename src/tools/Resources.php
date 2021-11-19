<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function exec;
use function getrusage;
use function ini_get;
use function is_string;
use function memory_get_usage;
use function microtime;
use function preg_match;
use function shell_exec;
use function strrev;
use function strtoupper;
use function sys_getloadavg;
use function trim;

/**
 * @see http://www.khmere.com/freebsd_book/html/ch07.html
 */
class Resources
{

    /** @var int */
    //public $blockOutput = 0;

    /** @var int */
    //public $blockInput = 0;

    /** @var int */
    //public $messagesSent = 0;

    /** @var int */
    //public $messagesReceived = 0;

    /** @var int */
    public $maxMemory;

    /** @var int */
    //public $sharedMemory = 0;

    /** @var int */
    //public $privateMemory = 0;

    /** @var int */
    //public $pageReclaims = 0;

    /** @var int */
    public $pageFaults;

    /** @var int */
    //public $signalsReceived = 0;

    /** @var int */
    //public $switches = 0;

    /** @var int */
    //public $invSwitches = 0;

    /** @var int */
    //public $swaps = 0;

    /** @var float */
    public $userTime;

    /** @var float */
    public $systemTime;

    /** @var float */
    public $time;

    /** @var int */
    public $phpMemory;

    /** @var int */
    public $realMemory;

    public static function get(): self
    {
        $that = new self();
        $that->time = microtime(true);
        $that->phpMemory = memory_get_usage();
        $that->realMemory = memory_get_usage(true);

        /** @var int[] $u */
        $u = getrusage();
        $that->maxMemory = $u['ru_maxrss'];
        $that->pageFaults = $u['ru_majflt'];
        $that->userTime = $u['ru_utime.tv_sec'] + ($u['ru_utime.tv_usec'] / 1000000);
        $that->systemTime = $u['ru_stime.tv_sec'] + ($u['ru_stime.tv_usec'] / 1000000);

        /*if (!System::isWindows()) {
            $that->blockOutput = $u['ru_oublock'];
            $that->blockInput = $u['ru_inblock'];
            $that->messagesSent = $u['ru_msgsnd'];
            $that->messagesReceived = $u['ru_msgrcv'];
            $that->sharedMemory = $u['ru_ixrss'];
            $that->privateMemory = $u['ru_idrss'];
            $that->pageReclaims = $u['ru_minflt'];
            $that->signalsReceived = $u['ru_nsignals'];
            $that->switches = $u['ru_nvcsw'];
            $that->invSwitches = $u['ru_nivcsw'];
            $that->swaps = $u['ru_nswap'];
        }*/

        return $that;
    }

    public function diff(self $other): self
    {
        $that = new self();
        $that->time = $this->time - $other->time;
        $that->phpMemory = $this->phpMemory - $other->phpMemory;
        $that->realMemory = $this->realMemory - $other->realMemory;
        $that->maxMemory = $this->maxMemory - $other->maxMemory;
        $that->pageFaults = $this->pageFaults - $other->pageFaults;
        $that->userTime = $this->userTime - $other->userTime;
        $that->systemTime = $this->systemTime - $other->systemTime;

        /*if (!System::isWindows()) {
            $that->blockOutput = $this->blockOutput - $other->blockOutput;
            $that->blockInput = $this->blockInput - $other->blockInput;
            $that->messagesSent = $this->messagesSent - $other->messagesSent;
            $that->messagesReceived = $this->messagesReceived - $other->messagesReceived;
            $that->sharedMemory = $this->sharedMemory - $other->sharedMemory;
            $that->privateMemory = $this->privateMemory - $other->privateMemory;
            $that->pageReclaims = $this->pageReclaims - $other->pageReclaims;
            $that->signalsReceived = $this->signalsReceived - $other->signalsReceived;
            $that->switches = $this->switches - $other->switches;
            $that->invSwitches = $this->invSwitches - $other->invSwitches;
            $that->swaps = $this->swaps - $other->swaps;
        }*/

        return $that;
    }

    // static helpers --------------------------------------------------------------------------------------------------

    public static function memoryLimit(): int
    {
        $m = strtoupper((string) ini_get('memory_limit'));
        $n = (int) $m;
        // spell-check-ignore: IB
        $m = trim(strrev($m), 'IB');
        if ($m[0] === 'K') {
            return $n * 1024;
        } elseif ($m[0] === 'M') {
            return $n * 1024 * 1024;
        } elseif ($m[0] === 'G') {
            return $n * 1024 * 1024 * 1024;
        } else {
            return $n;
        }
    }

    public static function memoryRemaining(): int
    {
        $memoryLimit = self::memoryLimit();
        $memoryUsed = memory_get_usage(false);

        return $memoryLimit - $memoryUsed;
    }

    public static function memoryRemainingRatio(): float
    {
        $memoryLimit = self::memoryLimit();
        $memoryUsed = memory_get_usage(false);

        return ($memoryLimit - $memoryUsed) / $memoryLimit;
    }

    public static function timeLimit(): float
    {
        return (float) ini_get('max_execution_time');
    }

    public static function timeUsed(): float
    {
        if (System::isWindows()) {
            // clock time
            return microtime(true) - Debugger::getStart();
        } else {
            // used cpu time
            /** @var int[] $u */
            $u = getrusage();
            return $u['ru_utime.tv_sec'] + ($u['ru_utime.tv_usec'] / 1000000)
                + $u['ru_stime.tv_sec'] + ($u['ru_stime.tv_usec'] / 1000000);
        }
    }

    public static function timeRemaining(): float
    {
        return self::timeLimit() - self::timeUsed();
    }

    public static function timeRemainingRatio(): float
    {
        $timeLimit = self::timeLimit();

        return ($timeLimit - self::timeUsed()) / $timeLimit;
    }

    public static function cpuLoad(): ?float
    {
        if (System::isWindows()) {
            // who the fuck knows...
            $cmd = "wmic cpu get loadpercentage /all";
            @exec($cmd, $output);

            if ($output) {
                foreach ($output as $line) {
                    if ($line && preg_match("/^[0-9]+\$/", $line)) {
                        return (float) $line;
                    }
                }
            }
        } else {
            // avg load for last 1 second
            $loads = sys_getloadavg() ?: [0.0];
            $cores = shell_exec("grep -P '^physical id' /proc/cpuinfo|wc -l");
            $cores = is_string($cores) ? (int) trim($cores) : 1;

            return ($loads[0] / $cores) / 100.0;
        }

        return null;
    }

}
