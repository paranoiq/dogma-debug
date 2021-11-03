<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function function_exists;
use function pcntl_async_signals;

class ProcessHandler
{

    /** @var bool */
    private static $enabled = false;

    public static function enable(): void
    {
        if (System::isWindows()) {
            return;
        }

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        // todo: pcntl_signal()

        self::$enabled = true;
    }

    public static function takeover(): void
    {
        // todo: pcntl_signal()
        // todo: pcntl_async_signals()
        // todo: pcntl_alarm()
        // todo: memory_limit()
        // todo: set_time_limit()
    }

    public static function disable(): void
    {

        self::$enabled = false;
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

}
