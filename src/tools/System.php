<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use const PHP_OS;

use function cli_set_process_title;
use function exec;
use function explode;
use function function_exists;
use function getmypid;
use function strtolower;
use function trim;
use function zend_thread_id;

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
            [, $columns] = explode(':', $output[4]);
            self::$columns = (int) trim($columns);
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

    public static function getId(): string
    {
        $id = (string) (int) getmypid();
        if (function_exists('zend_thread_id')) {
            $id .= '/' . zend_thread_id();
        }

        return $id;
    }

    public static function setProcessName(string $name): void
    {
        @cli_set_process_title($name);

        if (!self::isWindows()) {
            @file_put_contents("/proc/" . getmypid() . "/comm", $name);
        }
    }

}
