<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use LogicException;
use function file_get_contents;
use function get_included_files;
use function ob_end_flush;
use function ob_get_level;
use function ob_start;
use function strlen;

/**
 * Tracks output operations - echo, print() etc.
 */
class OutputHandler
{

    public const NAME = 'output';

    public const BOM = "\xEF\xBB\xBF";

    /** @var bool Print output samples */
    public static $printOutput = false;

    /** @var int Max length of printed output samples */
    public static $maxLength = 100;

    // internals -------------------------------------------------------------------------------------------------------

    /** @var bool */
    private static $enabled = false;

    /** @var bool */
    private static $init = false;

    /** @var int */
    private static $initialLevel;

    /** @var int */
    private static $length = 0;

    public static function enable(bool $showOutput = false): void
    {
        if (self::$enabled) {
            return;
        }
        self::$enabled = true;
        self::$initialLevel = ob_get_level();
        self::$printOutput = $showOutput;

        self::init();
        ob_start([self::class, 'handle'], 1);
    }

    public static function disable(): void
    {
        if (ob_get_level() !== self::$initialLevel + 1) {
            throw new LogicException('Cannot deterministically disable OutputHandler, because other code also messes with output buffering.');
        }
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function terminateAllOutputBuffers(): void
    {
        $n = ob_get_level();
        while ($n > 0) {
            ob_end_flush();
            $n--;
        }
    }

    public static function getTotalLength(): int
    {
        return self::$length;
    }

    /**
     * @return string|false
     */
    public static function handle(string $output, int $phase, ?Callstack $callstack = null)
    {
        if ($output === '') {
            return '';
        }
        self::$length += strlen($output);

        if (!self::$printOutput) {
            return false;
        }

        $oldMaxLength = Dumper::$config->maxLength;
        Dumper::$config->maxLength = self::$maxLength;
        $message = Ansi::white(' output: ', Ansi::DGREEN) . ' ' . Dumper::dumpString($output);
        Dumper::$config->maxLength = $oldMaxLength;

        $callstack = $callstack ?? Callstack::get(Dumper::$config->traceFilters);
        $backtrace = Dumper::formatCallstack($callstack, 1, 0, 0);

        Debugger::send(Message::STD_IO, $message, $backtrace);

        return false;
    }

    private static function init(): void
    {
        if (self::$init) {
            return;
        }
        self::$init = true;

        foreach (get_included_files() as $file) {
            $start = file_get_contents($file, false, null, 0, 3);
            if ($start === self::BOM) {
                $callstack = new Callstack([
                    new CallstackFrame($file, 1),
                ]);
                self::handle(self::BOM, 0, $callstack);
            }
        }
    }

}
