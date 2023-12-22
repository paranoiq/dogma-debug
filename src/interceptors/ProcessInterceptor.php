<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function implode;
use const SIG_DFL;

/**
 * Tracks signals and pcntl functions
 *
 * @see https://man7.org/linux/man-pages/man7/signal.7.html
 * @see https://stackoverflow.com/questions/3333276/signal-handling-on-windows
 */
class ProcessInterceptor
{

    public const NAME = 'process';

    /** @var int */
    private static $interceptSignals = Intercept::NONE;

    /** @var int */
    private static $interceptAlarm = Intercept::NONE;

    /** @var int */
    private static $interceptChildren = Intercept::NONE;

    /** @var int */
    private static $interceptKill = Intercept::NONE;

    /**
     * Intercept pcntl_signal(), pcntl_async_signals() and sapi_windows_set_ctrl_handler()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptSignals(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'pcntl_signal', self::class);
        Intercept::registerFunction(self::NAME, 'pcntl_async_signals', self::class);
        Intercept::registerFunction(self::NAME, 'pcntl_signal_dispatch', self::class);
        Intercept::registerFunction(self::NAME, 'pcntl_sigprocmask', self::class);
        Intercept::registerFunction(self::NAME, 'pcntl_sigwaitinfo', self::class);
        Intercept::registerFunction(self::NAME, 'pcntl_sigtimedwait', self::class);
        Intercept::registerFunction(self::NAME, 'pcntl_signal_get_handler', self::class);

        Intercept::registerFunction(self::NAME, 'sapi_windows_set_ctrl_handler', self::class);
        self::$interceptSignals = $level;
    }

    /**
     * intercept pcntl_alarm()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptAlarm(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'pcntl_alarm', self::class);
        self::$interceptAlarm = $level;
    }

    /**
     * Intercept pcntl_fork(), pcntl_waitpid()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptChildren(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'pcntl_fork', self::class);
        Intercept::registerFunction(self::NAME, 'pcntl_unshare', self::class);
        Intercept::registerFunction(self::NAME, 'pcntl_wait', self::class);
        Intercept::registerFunction(self::NAME, 'pcntl_waitpid', self::class);

        // info: pcntl_wifexited, pcntl_wifstopped, pcntl_wifsignaled, pcntl_wexitstatus, pcntl_wifcontinued, pcntl_wtermsig, pcntl_wstopsig
        //   pcntl_getpriority, pcntl_setpriority,
        // err.: pcntl_get_last_error, pcntl_errno, pcntl_strerror
        self::$interceptChildren = $level;
    }

    /**
     * Intercept posix_kill() function
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptPosixKill(int $level = Intercept::LOG_CALLS): void
    {
        Intercept::registerFunction(self::NAME, 'posix_kill', self::class);

        self::$interceptKill = $level;
    }

    // decorators ------------------------------------------------------------------------------------------------------

    /**
     * @param callable|int $callable
     */
    public static function pcntl_signal(int $signal, $callable, bool $restartSysCalls = true): bool
    {
        $name = ShutdownHandler::getSignalName($signal);
        $info = ' ' . Dumper::info("// {$name}");

        return Intercept::handle(self::NAME, self::$interceptSignals, __FUNCTION__, [$signal, $callable, $restartSysCalls], true, false, $info);
    }

    public static function pcntl_async_signals(?bool $enable): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSignals, __FUNCTION__, [$enable], true);
    }

    public static function pcntl_signal_dispatch(): bool
    {
        return Intercept::handle(self::NAME, self::$interceptSignals, __FUNCTION__, [], true);
    }

    /**
     * @param list<int> $signals
     * @param list<int> $old_signals
     */
    public static function pcntl_sigprocmask(int $mode, array $signals, &$old_signals): bool
    {
        $names = [];
        foreach ($signals as $signal) {
            $names = ShutdownHandler::getSignalName($signal);
        }
        $info = ' ' . Dumper::info('// ' . implode(',', $names));

        return Intercept::handle(self::NAME, self::$interceptSignals, __FUNCTION__, [$mode, $signals, &$old_signals], true, false, $info);
    }

    /**
     * @param list<int> $signals
     * @param array<string, int> $info
     * @return int|false
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public static function pcntl_sigwaitinfo(array $signals, &$info = [])
    {
        $names = [];
        foreach ($signals as $signal) {
            $names = ShutdownHandler::getSignalName($signal);
        }
        $info = ' ' . Dumper::info('// ' . implode(',', $names));

        return Intercept::handle(self::NAME, self::$interceptSignals, __FUNCTION__, [$signals, &$info], true, false, $info);
    }

    /**
     * @param list<int> $signals
     * @param array<string, int> $info
     * @return int|false
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public static function pcntl_sigtimedwait(array $signals, &$info = [], int $seconds = 0, int $nanoseconds = 0)
    {
        $names = [];
        foreach ($signals as $signal) {
            $names = ShutdownHandler::getSignalName($signal);
        }
        $info = ' ' . Dumper::info('// ' . implode(',', $names));

        return Intercept::handle(self::NAME, self::$interceptSignals, __FUNCTION__, [$signals, &$info, $seconds, $nanoseconds], true, false, $info);
    }

    public static function pcntl_signal_get_handler(int $signal)
    {
        $name = ShutdownHandler::getSignalName($signal);
        $info = ' ' . Dumper::info("// {$name}");

        return Intercept::handle(self::NAME, self::$interceptSignals, __FUNCTION__, [$signal], SIG_DFL, false, $info);
    }

    public static function sapi_windows_set_ctrl_handler(callable $callable, bool $add): bool
    {
        // @phpstan-ignore-next-line
        return Intercept::handle(self::NAME, self::$interceptSignals, __FUNCTION__, [$callable, $add], true);
    }

    public static function pcntl_alarm(int $seconds): int
    {
        return Intercept::handle(self::NAME, self::$interceptAlarm, __FUNCTION__, [$seconds], 0);
    }

    public static function pcntl_fork(): int
    {
        $pid = Intercept::handle(self::NAME, self::$interceptChildren, __FUNCTION__, [], 0);

        if ($pid !== 0) {
            Debugger::sendForkedProcessHeader(getmypid(), $pid);
        }

        return $pid;
    }

    public static function pcntl_unshare(int $flags): bool
    {
        // @phpstan-ignore-next-line
        return Intercept::handle(self::NAME, self::$interceptChildren, __FUNCTION__, [$flags], 0);
    }

    /**
     * @param int $status
     * @param mixed[] $resource_usage
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public static function pcntl_wait(&$status, int $flags = 0, &$resource_usage = []): int
    {
        return Intercept::handle(self::NAME, self::$interceptChildren, __FUNCTION__, [&$status, $flags, &$resource_usage], 0);
    }

    /**
     * @param int $status
     * @param mixed[] $resource_usage
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public static function pcntl_waitpid(int $process_id, &$status, int $flags = 0, &$resource_usage = []): int
    {
        return Intercept::handle(self::NAME, self::$interceptChildren, __FUNCTION__, [$process_id, &$status, $flags, &$resource_usage], 0);
    }

    public static function posix_kill(int $process_id, int $signal): bool
    {
        $name = ShutdownHandler::getSignalName($signal);
        $info = ' ' . Dumper::info("// {$name}");

        return Intercept::handle(self::NAME, self::$interceptKill, __FUNCTION__, [$process_id, $signal], true, false, $info);
    }

}
