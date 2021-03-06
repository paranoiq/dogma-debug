<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function cli_set_process_title;
use function exec;
use function explode;
use function file_put_contents;
use function function_exists;
use function getmypid;
use function str_contains;
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

        $os = strtolower(PHP_OS);

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
    public static function getIds(): array
    {
        return [(int) getmypid(), function_exists('zend_thread_id') ? zend_thread_id() : null];
    }

    public static function setProcessName(string $name): void
    {
        @cli_set_process_title($name);

        if (!self::isWindows()) {
            @file_put_contents("/proc/" . getmypid() . "/comm", $name);
        }
    }

}
