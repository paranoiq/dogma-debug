<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// spell-check-ignore: FI ZTS api eq ps pthreads tasklist tid

namespace Dogma\Debug;

use Thread;
use function cli_set_process_title;
use function exec;
use function explode;
use function extension_loaded;
use function file_put_contents;
use function function_exists;
use function getmypid;
use function shell_exec;
use function str_contains;
use function strpos;
use function strtolower;
use function trim;
use function zend_thread_id;
use const PHP_OS;

class System
{

    public static function isWindows(): bool
    {
        static $win;
        if ($win !== null) {
            return $win;
        }

        $os = strtolower(PHP_OS); // PHP_OS_FAMILY available since 7.2

        return $win = (str_contains($os, 'win') && !str_contains($os, 'darwin'));
    }

    public static function getTerminalWidth(): int
    {
        static $col;
        if ($col !== null) {
            return $col;
        }

        $col = (int) @exec('tput cols');
        if ($col) {
            return $col;
        }

        if (self::isWindows()) {
            exec('mode CON', $output);
            [, $col] = explode(':', $output[4]);
            $col = (int) trim($col);
        }

        return $col = $col ?: 120;
    }

    public static function switchTerminalToUtf8(): void
    {
        if (self::isWindows()) {
            exec('chcp 65001');
        }
    }

    /**
     * @return array{int, int|null}
     */
    public static function getProcessAndThreadId(): array
    {
        if (function_exists('zend_thread_id')) {
            // "This function is only available if PHP has been built with ZTS (Zend Thread Safety) support and debug mode (--enable-debug)."
            $tid = zend_thread_id();
        } elseif (extension_loaded('pthreads')) {
            $tid = Thread::getCurrentThreadId();
        } else {
            // parallel: no api to get thread id in parallel extension :E
            $tid = null;
        }

        return [(int) getmypid(), $tid];
    }

    public static function setProcessName(string $name): void
    {
        @cli_set_process_title($name);

        if (!self::isWindows()) {
            @file_put_contents("/proc/" . getmypid() . "/comm", $name);
        }
    }

    public static function processExists(int $pid): bool
    {
        if (self::isWindows()) {
            $output = shell_exec("tasklist /FI \"PID eq {$pid}\" 2>&1");
            if ($output === false || $output === null) { // @phpstan-ignore-line "Strict comparison using === between string|null and false will always evaluate to false."
                return true;
            }

            return strpos($output, "No tasks are running") === false;
        } else {
            $output = shell_exec("ps -p {$pid} 2>&1");
            if ($output === false || $output === null) { // @phpstan-ignore-line "Strict comparison using === between string|null and false will always evaluate to false."
                return true;
            }

            return strpos($output, "PID") !== false;
        }
    }

}
