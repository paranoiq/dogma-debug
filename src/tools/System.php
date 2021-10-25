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
use function strtolower;
use function trim;
use const PHP_OS;

class System
{

    /** @var int */
    private static $columns;

    public static function getTerminalWidth(): int
    {
        if (self::$columns) {
            return self::$columns;
        }

        self::$columns = (int) @exec('tput cols');
        if (self::$columns) {
            return self::$columns;
        }

        if (self::isWindows()) {
            exec('mode CON', $output);
            [, self::$columns] = explode(':', $output[4]);
            self::$columns = (int) trim(self::$columns);
        }

        return self::$columns ?: 120;
    }

    public static function switchTerminalToUtf8(): void
    {
        if (self::isWindows()) {
            exec('chcp 65001');
        }
    }

    public static function isWindows(): bool
    {
        $os = strtolower(PHP_OS);

        return Str::contains($os, 'win') && !Str::contains($os, 'darwin');
    }

    public static function setProcessName(string $name): void
    {
        @cli_set_process_title($name);

        if (!self::isWindows()) {
            @file_put_contents("/proc/" . getmypid() . "/comm", $name);
        }
    }

}
