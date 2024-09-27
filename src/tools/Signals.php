<?php

namespace Dogma\Debug;

use function array_search;
use function function_exists;
use function in_array;
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

class Signals
{

    /** @readonly @var array<string, int> */
    public static $all = [];

    /** @readonly @var int[] */
    public static $nonTerminating = [];

    /** @readonly @var int[] */
    public static $ignore = [];

    public static function init(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        self::$all = [
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
            self::$all['child'],
            self::$all['stop'],
            self::$all['term_stop'],
            self::$all['term_input'],
            self::$all['term_output'],
            self::$all['urgent'],
            self::$all['window_change'],
        ];
        self::$ignore = [
            // signal from finished processes started via exec() etc.
            self::$all['child'],
        ];
    }

    public static function getSignalName(int $signal): string
    {
        return array_search($signal, self::$all, true) ?: (string) $signal;
    }

    public static function isTerminating(int $signal): bool
    {
        return !in_array($signal, self::$nonTerminating, true);
    }

    public static function isHandlable(int $signal): bool
    {
        return $signal !== SIGKILL && $signal !== SIGSTOP;
    }

}
