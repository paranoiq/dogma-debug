<?php
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
use function is_a;
use function ltrim;
use function rtrim;
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
        Debugger::guarded(static function (): void {
            foreach (Callstack::get()->frames as $frame) {
                if (is_a($frame->class, TestCase::class, true) && str_starts_with($frame->function, 'test')) {
                    if (self::$useFullTestCaseName) {
                        $class = $frame->class;
                    } else {
                        $parts = explode('\\', $frame->class);
                        $class = end($parts);
                    }

                    /** @var TestCase $testCase */
                    $testCase = $frame->object;
                    $dataSetName = $testCase->dataSetAsString();

                    $args = Dumper::dumpArguments($frame->getNamedArgs());
                    $args = ltrim(rtrim($args, ','));
                    $message = Ansi::white(" Test case{$dataSetName}: ", Ansi::DGREEN) . ' ' . Dumper::class($class) . '::' . Dumper::function($frame->function) . '(' . $args . '): ';

                    if ($message !== self::$currentTestCaseName) {
                        Debugger::send(Message::CALLSTACK, $message);
                    }

                    self::$currentTestCaseName = $message;
                }
            }
        }, __CLASS__, __FUNCTION__);
    }

}
