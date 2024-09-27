<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function array_search;

/**
 * special bash exit codes: https://tldp.org/LDP/abs/html/exitcodes.html
 * special C exit codes: https://www.apt-browse.org/browse/ubuntu/trusty/main/amd64/libc6-dev/2.19-0ubuntu6/file/usr/include/sysexits.h
 */
class ExecInterceptor
{

    public const NAME = 'exec';

    /** @var int */
    private static $interceptExec = Intercept::NONE;

    /** @var array<int, string> */
    private static $exitCodes = [
        0 => 'OK',
        1 => 'General error',
        2 => 'Misuse of shell builtins',
        // User-defined errors must use exit codes in the 64-113 range
        126 => 'Invoked command cannot execute',
        127 => 'Command not found',
        128 => 'Invalid exit argument',

        // signals
        129 => 'Hangup',
        130 => 'Interrupt',
        131 => 'Quit and dump core',
        132 => 'Illegal instruction',
        133 => 'Trace/breakpoint trap',
        134 => 'Process aborted',
        135 => 'Bus error: "access to undefined portion of memory object"',
        136 => 'Floating point exception: "erroneous arithmetic operation"',
        137 => 'Kill (terminate immediately)',
        138 => 'User-defined 1',
        139 => 'Segmentation violation',
        140 => 'User-defined 2',
        141 => 'Write to pipe with no one reading',
        142 => 'Signal raised by alarm',
        143 => 'Termination (request to terminate)',
        // 144 - not defined
        145 => 'Child process terminated, stopped (or continued*)',
        146 => 'Continue if stopped',
        147 => 'Stop executing temporarily',
        148 => 'Terminal stop signal',
        149 => 'Background process attempting to read from tty ("in")',
        150 => 'Background process attempting to write to tty ("out")',
        151 => 'Urgent data available on socket',
        152 => 'CPU time limit exceeded',
        153 => 'File size limit exceeded',
        154 => 'Signal raised by timer counting virtual time: "virtual timer expired"',
        155 => 'Profiling timer expired',
        // 156 - not defined
        157 => 'Pollable event',
        // 158 - not defined
        159 => 'Bad syscall',
    ];

    public static function getExitCodeName(int $exitCode): string
    {
        return array_search($exitCode, self::$exitCodes, true) ?: 'Unknown exit code';
    }

    /**
     * Intercept exec(), passthru(), pcntl_exec(), popen(), proc_open(), shell_exec(), system()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptExec(int $level = Intercept::LOG_CALLS): void
    {
        if ($level & Intercept::ANNOUNCE) {
            Debugger::dependencyInfo("Registered interceptors for process executing functions.");
        }

        Intercept::registerFunction(self::NAME, 'exec', self::class);
        Intercept::registerFunction(self::NAME, 'passthru', self::class);
        Intercept::registerFunction(self::NAME, 'pcntl_exec', self::class);
        Intercept::registerFunction(self::NAME, 'popen', self::class);
        Intercept::registerFunction(self::NAME, 'proc_open', self::class);
        Intercept::registerFunction(self::NAME, 'shell_exec', self::class);
        Intercept::registerFunction(self::NAME, 'system', self::class);

        // todo: `foo`

        Intercept::registerFunction(self::NAME, 'proc_close', self::class);
        Intercept::registerFunction(self::NAME, 'proc_terminate', self::class);
        Intercept::registerFunction(self::NAME, 'proc_get_status', self::class);

        self::$interceptExec = $level;
    }

    // decorators ------------------------------------------------------------------------------------------------------

    /**
     * @param list<string> $output
     * @param int $result_code
     * @return string|false
     */
    public static function exec(string $command, &$output, &$result_code)
    {
        return Intercept::handle(self::NAME, self::$interceptExec, __FUNCTION__, [$command, &$output, &$result_code], '');
    }

    /**
     * @return false|never
     */
    public static function pcntl_exec(string $path, array $args = [], array $env_vars = []): bool
    {
        return Intercept::handle(self::NAME, self::$interceptExec, __FUNCTION__, [$path, $args, $env_vars], true);
    }

    /**
     * @param int $result_code
     * @return false|null
     */
    public static function passthru(string $command, &$result_code): ?bool
    {
        return Intercept::handle(self::NAME, self::$interceptExec, __FUNCTION__, [$command, &$result_code], null);
    }

    /**
     * @return resource|false
     */
    public static function popen(string $command, string $mode)
    {
        return Intercept::handle(self::NAME, self::$interceptExec, __FUNCTION__, [$command, $mode], false);
    }

    /**
     * @param string|list<string> $command
     * @param array<int, resource|array{string, string}> $descriptor_spec
     * @param array<int, resource> $pipes
     * @param string|null $cwd
     * @param array<string, string>|null $env_vars
     * @param array<string, bool>|null $options
     * @return resource|false
     */
    public static function proc_open($command, array $descriptor_spec, &$pipes, ?string $cwd = null, ?array $env_vars = null, ?array $options = null)
    {
        return Intercept::handle(self::NAME, self::$interceptExec, __FUNCTION__, [$command, $descriptor_spec, &$pipes, $cwd, $env_vars, $options], false);
    }

    /**
     * @param resource $process
     */
    public static function proc_close($process): int
    {
        return Intercept::handle(self::NAME, self::$interceptExec, __FUNCTION__, [$process], 0);
    }

    public static function proc_terminate($process, int $signal = 15): bool
    {
        $name = Signals::getSignalName($signal);
        $info = ' ' . Dumper::info("// {$name}");

        return Intercept::handle(self::NAME, self::$interceptExec, __FUNCTION__, [$process, $signal], true, false, $info);
    }

    public static function proc_get_status($process)
    {
        return Intercept::handle(self::NAME, self::$interceptExec, __FUNCTION__, [$process], false);
    }

    /**
     * @return string|false|null
     */
    public static function shell_exec(string $command)
    {
        return Intercept::handle(self::NAME, self::$interceptExec, __FUNCTION__, [$command], null);
    }

    /**
     * @param int $result_code
     * @return string|false
     */
    public static function system(string $command, &$result_code)
    {
        return Intercept::handle(self::NAME, self::$interceptExec, __FUNCTION__, [$command, &$result_code], '');
    }

}
