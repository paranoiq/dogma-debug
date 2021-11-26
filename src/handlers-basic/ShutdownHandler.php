<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use LogicException;
use const SIG_DFL;
use const SIGABRT;
use const SIGALRM;
use const SIGBUS;
use const SIGCHLD;
use const SIGCONT;
use const SIGFPE;
use const SIGHUP;
use const SIGILL;
use const SIGINT;
use const SIGIO;
use const SIGKILL;
use const SIGPIPE;
use const SIGPROF;
use const SIGPWR;
use const SIGQUIT;
use const SIGSEGV;
use const SIGSTKFLT;
use const SIGSTOP;
use const SIGSYS;
use const SIGTERM;
use const SIGTRAP;
use const SIGTSTP;
use const SIGTTIN;
use const SIGTTOU;
use const SIGURG;
use const SIGUSR1;
use const SIGUSR2;
use const SIGVTALRM;
use const SIGWINCH;
use const SIGXCPU;
use const SIGXFSZ;
use function array_search;
use function func_get_args;
use function function_exists;
use function ignore_user_abort;
use function in_array;
use function pcntl_async_signals;
use function pcntl_signal;
use function sapi_windows_set_ctrl_handler;

/**
 * Tracks signals, exit() and die() and tries to determine what lead to process termination
 *
 * PHP request shutdown steps:
 * - call all functions registered via register_shutdown_function()
 * - call all* __destruct() methods
 * - empty all output buffers
 * - end all PHP extensions (e.g. sessions)
 * - turn off output layer (send HTTP headers, terminate output handlers etc.)
 *
 * @see https://phpfashion.com/jak-probiha-shutdown-v-php-a-volani-destruktoru
 *
 * @see https://man7.org/linux/man-pages/man7/signal.7.html
 * @see https://stackoverflow.com/questions/3333276/signal-handling-on-windows
 */
class ShutdownHandler
{

    public const NAME = 'shutdown';

    /** @var bool */
    private static $enabled = false;

    /** @var int */
    private static $interceptSignals = Intercept::NONE;

    /** @var int */
    private static $interceptExit = Intercept::NONE;

    /** @var int */
    private static $interceptShutdown = Intercept::NONE;

    /** @var int */
    private static $interceptAbort = Intercept::NONE;

    /** @var array<string, int> */
    private static $signals;

    /** @var int[] */
    private static $nonTerminating;

    /**
     * @param int[] $ignoreSignals
     */
    public static function enable(array $ignoreSignals = []): void
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        if (function_exists('pcntl_signal')) {
            self::$signals = [
                // (~ system depend., * core dump) N Action       Description (Synonym)
                'hangup' => SIGHUP,           //   1 Terminate    Hangup
                'interrupt' => SIGINT,        //   2 Terminate    Terminal interrupt signal
                'quit' => SIGQUIT,            //   3 Terminate*   Terminal quit signal
                'illegal' => SIGILL,          //   4 Terminate*   Illegal instruction
                'trace' => SIGTRAP,           //   5 Terminate*   Trace/breakpoint trap
                'abort' => SIGABRT,           //   6 Terminate*   Process abort signal (SIGIOT)
                'bus_error' => SIGBUS,        //  ~7 Terminate*   Access to an undefined portion of a memory object
                'fpe' => SIGFPE,              //   8 Terminate*   Erroneous arithmetic operation
                'kill' => SIGKILL,            //   9 Terminate    Kill (cannot be caught or ignored)
                'user_1' => SIGUSR1,          // ~10 Terminate    User-defined signal 1
                'segfault' => SIGSEGV,        //  11 Terminate*   Invalid memory reference
                'user_2' => SIGUSR2,          // ~12 Terminate    User-defined signal 2
                'pipe' => SIGPIPE,            //  13 Terminate    Write on a pipe with no one to read it
                'alarm' => SIGALRM,           //  14 Terminate    Alarm clock
                'terminate' => SIGTERM,       //  15 Terminate    Termination signal
                'stack_fault' => SIGSTKFLT,   // ~16 Terminate    Stack fault on coprocessor
                'child' => SIGCHLD,           // ~17 Ignore       Child process terminated, stopped, or continued (SIGCLD)
                'continue' => SIGCONT,        // ~18 Continue     Continue executing, if stopped
                'stop' => SIGSTOP,            // ~19 Stop         Stop executing (cannot be caught or ignored)
                'term_stop' => SIGTSTP,       // ~20 Stop         Terminal stop signal
                'term_input' => SIGTTIN,      // ~21 Stop         Background process attempting read
                'term_output' => SIGTTOU,     // ~22 Stop         Background process attempting write
                'urgent' => SIGURG,           // ~23 Ignore       Out-of-band data is available at a socket
                'cpu' => SIGXCPU,             // ~24 Terminate*   CPU time limit exceeded
                'file_size' => SIGXFSZ,       // ~25 Terminate*   File size limit exceeded
                'virtual_alarm' => SIGVTALRM, // ~26 Terminate    Virtual timer expired
                'profiling' => SIGPROF,       // ~27 Terminate    Profiling timer expired
                'window_change' => SIGWINCH,  // ~28 Ignore       Terminal window size changed
                'io' => SIGIO,                // ~29 Terminate    I/O now possible (SIGPOLL)
                'power' => SIGPWR,            // ~30 Terminate    Power failure (SIGINFO)
                'system_call' => SIGSYS,      // ~31 Terminate*   Bad system call (SIGUNUSED)
            ];
            self::$nonTerminating = [
                self::$signals['child'],
                self::$signals['stop'],
                self::$signals['term_stop'],
                self::$signals['term_input'],
                self::$signals['term_output'],
                self::$signals['urgent'],
                self::$signals['window_change'],
            ];

            // cannot set handler
            $ignoreSignals[] = self::$signals['kill'];
            $ignoreSignals[] = self::$signals['stop'];

            // handled by other handler
            if (ResourcesHandler::enabled()) {
                $ignoreSignals[] = self::$signals['alarm'];
            }

            foreach (self::$signals as $signal) {
                if (in_array($signal, $ignoreSignals, true)) {
                    continue;
                }
                pcntl_signal($signal, [self::class, 'signal']);
            }
        } elseif (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler([self::class, 'winSignal'], true);
        } else {
            return;
        }

        self::$enabled = true;
    }

    public static function disable(): void
    {
        if (function_exists('pcntl_signal')) {
            foreach (self::$signals as $signal) {
                // set back to default
                pcntl_signal($signal, SIG_DFL);
            }
        } elseif (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler([self::class, 'winSignal'], false);
        } else {
            return;
        }

        self::$enabled = false;
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    /**
     * @param mixed $info
     */
    public static function signal(int $signal, $info): void
    {
        $name = array_search($signal, self::$signals, true) ?: $signal;
        Debugger::setTermination("signal ($name)");

        if (!in_array($signal, self::$nonTerminating, true)) {
            //Debugger::send(Packet::ERROR, Ansi::white(" Terminated by $name signal. ", Ansi::DRED));
            exit;
        } else {
            Debugger::send(Packet::ERROR, Ansi::white(" Signal $name received. ", Ansi::DMAGENTA));
        }
    }

    /**
     * @param int $signal (PHP_WINDOWS_EVENT_CTRL_C | PHP_WINDOWS_EVENT_CTRL_BREAK)
     */
    public static function winSignal(int $signal): void
    {
        $name = $signal === PHP_WINDOWS_EVENT_CTRL_C ? 'ctrl-c'
            : ($signal === PHP_WINDOWS_EVENT_CTRL_BREAK ? 'ctrl-break' : 'unknown');

        Debugger::setTermination("signal($name)");

        //Debugger::send(Packet::ERROR, Ansi::white(" Terminated by $name signal. ", Ansi::DRED));
        exit;
    }

    // intercept handlers ----------------------------------------------------------------------------------------------

    /**
     * Take control over pcntl_signal(), pcntl_async_signals() and sapi_windows_set_ctrl_handler()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptSignals(int $level = Intercept::LOG_CALLS): void
    {
        self::$interceptSignals = $level;
        Intercept::register(self::NAME, 'pcntl_signal', [self::class, 'fakeSignal']);
        Intercept::register(self::NAME, 'pcntl_async_signals', [self::class, 'fakeAsyncSignals']);
        Intercept::register(self::NAME, 'sapi_windows_set_ctrl_handler', [self::class, 'fakeWinSignal']);
    }

    /**
     * Takes control over exit() and die()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptExit(int $level = Intercept::LOG_CALLS): void
    {
        self::$interceptExit = $level;
        Intercept::register(self::NAME, 'exit', [self::class, 'fakeExit']);
        Intercept::register(self::NAME, 'die', [self::class, 'fakeExit']); // die() is just synonym of exit()
    }

    /**
     * Take control over ignore_user_abort()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptAbort(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::register(self::NAME, 'ignore_user_abort', [self::class, 'fakeIgnore']);
        self::$interceptAbort = $level;
    }

    /**
     * Takes control over register_shutdown_function()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptShutdown(int $level = Intercept::LOG_CALLS): void
    {
        self::$interceptShutdown = $level;
        Intercept::register(self::NAME, 'register_shutdown_function', [self::class, 'fakeRegister']);
    }

    /**
     * @param callable|int $callable
     */
    public static function fakeSignal(int $signal, $callable, bool $restartSysCalls = true): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSignals, 'pcntl_signal', [$signal, $callable, $restartSysCalls], true);
    }

    public static function fakeAsyncSignals(?bool $enable): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSignals, 'pcntl_async_signals', [$enable], true);
    }

    public static function fakeWinSignal(callable $callable, bool $add): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSignals, 'sapi_windows_set_ctrl_handler', [$callable, $add], true);
    }

    /**
     * @param string|int $status
     */
    public static function fakeExit($status = ''): void
    {
        Debugger::setTermination($status ? 'exit (' . $status . ')' : 'exit');

        if (self::$interceptExit === Intercept::SILENT) {
            exit($status);
        } elseif (self::$interceptExit === Intercept::LOG_CALLS) {
            Intercept::log(self::NAME, self::$interceptExit, 'exit', [$status], null);
            exit($status);
        } else {
            throw new LogicException('Not implemented.');
        }
    }

    /**
     * @param mixed ...$args
     */
    public static function fakeRegister(?callable $callback, ...$args): ?bool
    {
        return Intercept::handle(self::NAME, self::$interceptShutdown, 'register_shutdown_function', func_get_args(), null);
    }

    public static function fakeIgnore(?bool $ignore): int
    {
        return Intercept::handle(self::NAME, self::$interceptAbort, 'ignore_user_abort', [$ignore], ignore_user_abort());
    }

}
