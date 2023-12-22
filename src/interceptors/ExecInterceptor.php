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
 * special bash exit codes: https://tldp.org/LDP/abs/html/exitcodes.html
 * special C exit codes: https://www.apt-browse.org/browse/ubuntu/trusty/main/amd64/libc6-dev/2.19-0ubuntu6/file/usr/include/sysexits.h
 */
class ExecInterceptor
{

    public const NAME = 'exec';

    /** @var int */
    private static $interceptExec = Intercept::NONE;

    /**
     * Intercept exec(), passthru(), pcntl_exec(), popen(), proc_open(), shell_exec(), system()
     *
     * @param int $level Intercept::SILENT|Intercept::LOG_CALLS|intercept::PREVENT_CALLS
     */
    public static function interceptExec(int $level = Intercept::LOG_CALLS): void
    {
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
     * @return null|false
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
    public static function proc_open($command, array $descriptor_spec, &$pipes, ?string $cwd, ?array $env_vars, ?array $options)
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
        $name = ShutdownHandler::getSignalName($signal);
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
