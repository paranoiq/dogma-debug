<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use PHPUnit\Framework\TestCase;
use function end;
use function explode;
use function str_starts_with;

class PhpUnitHandler
{

    /** @var bool */
    public static $announceTestCaseName = true;

    /** @var bool */
    public static $useFullTestCaseName = false;

    /** @var string|null */
    private static $currentTestCaseName;

    public static function announceTestCaseName(): void
    {
        Debugger::guarded(static function () {
            foreach (Callstack::get()->frames as $frame) {
                if (is_a($frame->class, TestCase::class, true) && str_starts_with($frame->function, 'test')) {
                    if (self::$useFullTestCaseName) {
                        $class = $frame->class;
                    } else {
                        $parts = explode('\\', $frame->class);
                        $class = end($parts);
                    }
                    $testCaseName = "{$class}::{$frame->function}";

                    if (self::$currentTestCaseName === $testCaseName) {
                        return;
                    }

                    $message = Ansi::white(" Test case {$testCaseName}: ", Ansi::DGREEN);

                    Debugger::send(Packet::CALLSTACK, $message);

                    self::$currentTestCaseName = $testCaseName;
                }
            }
        }, __CLASS__, __FUNCTION__);
    }
}
